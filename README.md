SummarAIzer

SummarAIzer is a PHP-based web application that helps users quickly summarize long pieces of text. It uses basic machine learning techniques to extract the most important sentences and present them in a shorter and easier-to-read form. This project was developed as a final requirement for ITEP 308 System Integration and Architecture I during the first semester of Academic Year 2025–2026.

The main goal of this project is to help students and researchers save time when reading long articles, research papers, or study materials. Instead of reading everything, users can paste their text into the system and receive a concise summary.

The system provides machine learning powered summarization using the TF-IDF algorithm from the PHP-ML library. It allows users to choose how the summary is shown, either as a paragraph or as bullet points, and to adjust the summary length based on their needs. A real-time word counter is included, and the generated summary can be copied easily. The interface supports both light and dark mode for better readability.

SummarAIzer works by following a simple client-server architecture. Users interact with the system through a web interface built with HTML, CSS, and JavaScript. When text is submitted, it is sent to the PHP backend through an HTTP request. The backend processes the text using the PHP-ML library by splitting the text into sentences, removing common stop words, converting the text into feature vectors, and calculating TF-IDF scores. Sentences are then ranked based on importance, and the most relevant ones are selected to form the final summary.

The summarization approach used in this system is extractive, meaning the summary is created by selecting important sentences directly from the original text rather than generating new sentences.

The application was built using PHP for the backend and HTML, CSS, and JavaScript for the frontend. Composer is used to manage dependencies, and the system follows a simple and organized client-server setup.

This project was developed by the following team members:
Tolentino, Cathlene A.
Tolentino, Lawrence Dave P.
Valenzuela, John Oliver R.

SummarAIzer is intended for educational use and was created to demonstrate system integration, basic machine learning usage, and web application development. The project can be deployed on any hosting service that supports PHP.


Link to deployed system: summaraizer-production.up.railway.app

Link to video presentation: https://youtu.be/pz7WIdnSk8k

Link to Canva/PowerPoint: https://www.canva.com/design/DAG7fdhtuHs/ln_H5e8Cx9KveCYxzA8ezg/edit?utm_content=DAG7fdhtuHs&utm_campaign=designshare&utm_medium=link2&utm_source=sharebutton