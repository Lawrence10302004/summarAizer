<?php
header("Content-Type: text/html; charset=utf-8");

// Suppress deprecation warnings from vendor library (php-ml uses deprecated ${var} syntax in StopWords.php:28)
// This warning appears when the StopWords class is used - keep suppression active throughout execution
// The library still works correctly, this is just a PHP 8.2+ deprecation notice
// We suppress E_DEPRECATED but keep other errors visible for debugging
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Load Composer autoloader for ML library
require_once __DIR__ . '/../vendor/autoload.php';

use Phpml\Tokenization\WordTokenizer;
use Phpml\FeatureExtraction\StopWords\English;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\FeatureExtraction\TfIdfTransformer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed. Use POST.";
    exit;
}

$text = trim($_POST['text'] ?? '');
$ratio = $_POST['ratio'] ?? 'medium';
$mode  = $_POST['mode'] ?? 'paragraph';

if ($text === '') {
    http_response_code(400);
    echo "No text provided.";
    exit;
}

// Split text into paragraphs first
$paragraphs = preg_split('/\n\s*\n/', $text);
$paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

// If no paragraph breaks found, treat entire text as one paragraph
if (count($paragraphs) === 0 && strlen($text) > 0) {
    $paragraphs = [$text];
}

// Split each paragraph into sentences and track which paragraph each sentence belongs to
$sentences = [];
$sentenceToParagraph = []; // Maps sentence index to paragraph index
$sentenceIndex = 0;

foreach ($paragraphs as $paraIndex => $paragraph) {
    $paraSentences = preg_split('/(?<=[.?!])\s+/', $paragraph);
    $paraSentences = array_values(array_filter(array_map('trim', $paraSentences)));
    
    if (empty($paraSentences) && strlen($paragraph) > 0) {
        // If paragraph has no sentence endings, treat whole paragraph as one sentence
        $paraSentences = [$paragraph];
    }
    
    foreach ($paraSentences as $sentence) {
        $sentences[] = $sentence;
        $sentenceToParagraph[$sentenceIndex] = $paraIndex;
        $sentenceIndex++;
    }
}

$total = count($sentences);
if ($total === 0 && strlen($text) > 0) {
    $sentences = [$text];
    $sentenceToParagraph[0] = 0;
    $total = 1;
}

// Initialize ML components
$tokenizer = new WordTokenizer();
$stopWords = new English();
$vectorizer = new TokenCountVectorizer($tokenizer, $stopWords, 0.0);
$tfIdfTransformer = new TfIdfTransformer();

// Prepare sentences for ML processing (normalize to lowercase)
$normalizedSentences = array_map(function($s) {
    return mb_strtolower($s, 'UTF-8');
}, $sentences);

// Calculate word counts for each sentence (before vectorization)
$sentenceWordCounts = [];
foreach ($normalizedSentences as $i => $sentence) {
    $sentenceWords = $tokenizer->tokenize($sentence);
    $sentenceWordCounts[$i] = max(1, count($sentenceWords));
}

// Fit and transform using ML library
$vectorizer->fit($normalizedSentences);
$vectorizedSentences = $normalizedSentences;
$vectorizer->transform($vectorizedSentences);

// Apply TF-IDF transformation
$tfIdfTransformer->fit($vectorizedSentences);
$tfIdfTransformer->transform($vectorizedSentences);

// Score sentences based on TF-IDF values
$scores = [];
foreach ($vectorizedSentences as $i => $vector) {
    $score = 0;
    // Sum all TF-IDF values for the sentence
    foreach ($vector as $tfIdfValue) {
        $score += abs($tfIdfValue); // Use absolute value to handle any negative scores
    }
    
    // Normalize by sentence length (word count)
    $wordCount = $sentenceWordCounts[$i];
    $baseScore = $score / $wordCount;
    
    // Position weighting (first and last sentences are often important)
    $positionWeight = 1.0;
    if ($i === 0) $positionWeight = 1.5;
    elseif ($i === $total - 1) $positionWeight = 1.3;
    elseif ($i > 0 && $i < $total - 1) $positionWeight = 1.1;

    $scores[$i] = $baseScore * $positionWeight;
}

// Helper: pick number of sentences
function pickCountByRatio($ratio, $total) {
    switch ($ratio) {
        case 'short':  return max(1, (int)round($total * 0.25));
        case 'medium': return max(1, (int)round($total * 0.4));
        case 'long':   return max(1, (int)round($total * 0.6));
        default:       return max(1, (int)round($total * 0.4));
    }
}

// Summarize helper: group sentences by paragraph and maintain original order
function summarizeSentences($topIndexes, $sentences, $sentenceToParagraph) {
    sort($topIndexes);
    
    // Group selected sentences by their original paragraph
    $paragraphGroups = [];
    foreach ($topIndexes as $i) {
        $paraIndex = $sentenceToParagraph[$i] ?? 0;
        if (!isset($paragraphGroups[$paraIndex])) {
            $paragraphGroups[$paraIndex] = [];
        }
        $paragraphGroups[$paraIndex][] = $i;
    }
    
    // Sort paragraphs by their original order
    ksort($paragraphGroups);
    
    // Build result: sentences within same paragraph are grouped, paragraphs are separated
    $result = '';
    foreach ($paragraphGroups as $paraIndex => $sentenceIndexes) {
        $paragraphText = '';
        foreach ($sentenceIndexes as $i) {
            if ($paragraphText !== '') {
                $paragraphText .= ' '; // Space between sentences in same paragraph
            }
            $paragraphText .= htmlspecialchars($sentences[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $result .= '<p class="summary-paragraph">' . $paragraphText . '</p>';
    }
    
    return $result;
}

// Controlled variation: add tiny random noise for tie-breaking
$scoreNoise = [];
foreach ($scores as $i => $score) {
    $scoreNoise[$i] = $score + mt_rand(0, 5) / 1000; // small noise for variety
}

// Sort by adjusted score descending
arsort($scoreNoise);

// Paragraph mode
if ($mode === 'paragraph') {
    $summaryLength = pickCountByRatio($ratio, $total);
    $topIndexes = array_slice(array_keys($scoreNoise), 0, $summaryLength);
    echo summarizeSentences($topIndexes, $sentences, $sentenceToParagraph);
    exit;
}

// Keypoints/bullet mode
if ($mode === 'keypoints' || $mode === 'bullet') {
    $keyCount = pickCountByRatio($ratio, $total);
    $topIndexes = array_slice(array_keys($scoreNoise), 0, $keyCount);
    sort($topIndexes);
    
    // Group bullet points by paragraph
    $paragraphGroups = [];
    foreach ($topIndexes as $i) {
        $paraIndex = $sentenceToParagraph[$i] ?? 0;
        if (!isset($paragraphGroups[$paraIndex])) {
            $paragraphGroups[$paraIndex] = [];
        }
        $paragraphGroups[$paraIndex][] = $i;
    }
    
    // Sort paragraphs by their original order
    ksort($paragraphGroups);
    
    // Build result: bullets within same paragraph are grouped, paragraphs are separated
    $result = '';
    foreach ($paragraphGroups as $paraIndex => $sentenceIndexes) {
        $paragraphText = '';
        foreach ($sentenceIndexes as $i) {
            if ($paragraphText !== '') {
                $paragraphText .= '<br>'; // Line break between bullets in same paragraph
            }
            $paragraphText .= "• " . htmlspecialchars($sentences[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $result .= '<p class="summary-paragraph">' . $paragraphText . '</p>';
    }
    
    echo $result;
    exit;
}

// Custom mode
if ($mode === 'custom') {
    if ($customCount < 1) {
        http_response_code(400);
        echo "Please enter a valid number of sentences.";
        exit;
    }
    $customCount = min($customCount, $total);
    $topIndexes = array_slice(array_keys($scoreNoise), 0, $customCount);
    echo summarizeSentences($topIndexes, $sentences, $sentenceToParagraph);
    exit;
}

http_response_code(400);
echo "Unknown mode.";
exit;
?>
