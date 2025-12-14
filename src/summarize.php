<?php
header("Content-Type: text/html; charset=utf-8");

// Suppress deprecation warnings from vendor library (php-ml uses deprecated ${var} syntax)
// This will be fixed when the library is updated
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

// Split sentences
$sentences = preg_split('/(?<=[.?!])\s+/', $text);
$sentences = array_values(array_filter(array_map('trim', $sentences)));
$total = count($sentences);
if ($total === 0 && strlen($text) > 0) {
    $sentences = [$text];
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

// Summarize helper: maintain original order
function summarizeSentences($topIndexes, $sentences) {
    sort($topIndexes);
    $result = '';
    foreach ($topIndexes as $i) {
        $result .= htmlspecialchars($sentences[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>";
    }
    return trim($result);
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
    echo summarizeSentences($topIndexes, $sentences);
    exit;
}

// Keypoints/bullet mode
if ($mode === 'keypoints' || $mode === 'bullet') {
    $keyCount = pickCountByRatio($ratio, $total);
    $topIndexes = array_slice(array_keys($scoreNoise), 0, $keyCount);
    sort($topIndexes);
    $result = '';
    foreach ($topIndexes as $i) {
        $result .= "• " . htmlspecialchars($sentences[$i], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br>";
    }
    echo trim($result);
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
    echo summarizeSentences($topIndexes, $sentences);
    exit;
}

http_response_code(400);
echo "Unknown mode.";
exit;
?>
