<?php
require_once("db.php");

// Fetch all .txt files from the given directory
function getTextFiles($directory)
{
    $files = [];
    if ($handle = opendir($directory)) {
        while (($file = readdir($handle)) !== false) {
            if ($file !== "." && $file !== ".." && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
                $files[] = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            }
        }
        closedir($handle);
    }
    return $files;
}

// Read stopwords from the stoplist file
function getStopwords($stoplistFile)
{
    $stopwords = [];
    if (file_exists($stoplistFile)) {
        $stopwords = array_map('trim', file($stoplistFile, FILE_IGNORE_NEW_LINES));
    }
    return array_map('strtolower', $stopwords); // Ensure stopwords are in lowercase
}

// Remove stopwords from the text
function removeStopwords($text, $stopwords)
{
    $text = preg_replace('/[^\w\s\'"]/', '', $text); // Remove unwanted characters but keep single and double quotes
    $words = preg_split('/\s+/', $text); // Split by whitespace
    $filteredWords = array_filter($words, function ($word) use ($stopwords) {
        return !in_array(strtolower($word), $stopwords);
    });
    return implode(' ', $filteredWords);
}

// Lemmatize text using Python's Spacy script with temporary files
function lemmatizeWithPython($text)
{
    $inputFile = tempnam(sys_get_temp_dir(), 'input_');
    $outputFile = tempnam(sys_get_temp_dir(), 'output_');

    file_put_contents($inputFile, $text);

    $command = escapeshellcmd("python3 lemmatizer.py $inputFile $outputFile");
    shell_exec($command);

    if (!file_exists($outputFile)) {
        echo "Error: Python script did not generate output.\n";
        unlink($inputFile);
        return [];
    }

    $lemmas = file_get_contents($outputFile);

    unlink($inputFile);
    unlink($outputFile);

    $decoded = json_decode($lemmas, true);

    if ($decoded === null) {
        echo "Error decoding JSON from Python script: " . json_last_error_msg() . "\n";
        return [];
    }

    return array_map('strtolower', $decoded); // Ensure lemmas are in lowercase
}

// Insert or retrieve a term ID
function insertTerm($db, $term)
{
    $stmt = $db->prepare("INSERT IGNORE INTO terms (term) VALUES (:term)");
    $stmt->execute(['term' => $term]);

    $stmt = $db->prepare("SELECT id FROM terms WHERE term = :term");
    $stmt->execute(['term' => $term]);
    return $stmt->fetchColumn();
}

// Insert or retrieve a document ID
function insertDocument($db, $filePath)
{
    $stmt = $db->prepare("INSERT IGNORE INTO documents (file_path) VALUES (:file_path)");
    $stmt->execute(['file_path' => $filePath]);

    $stmt = $db->prepare("SELECT id FROM documents WHERE file_path = :file_path");
    $stmt->execute(['file_path' => $filePath]);
    return $stmt->fetchColumn();
}

// Insert positions into the database
function insertPosition($db, $termId, $documentId, $position)
{
    $stmt = $db->prepare("INSERT INTO positions (term_id, document_id, position) VALUES (:term_id, :document_id, :position)");
    $stmt->execute([
        'term_id' => $termId,
        'document_id' => $documentId,
        'position' => $position,
    ]);
}

// Read content from a file and tokenize with positions
function getFileContentWithPositions($filePath)
{
    $tokensWithPositions = [];
    $content = file_get_contents($filePath);

    // Split the content into words while preserving positions
    $words = preg_split('/\s+/', $content);

    foreach ($words as $originalPosition => $word) {
        $cleanedWord = preg_replace('/[^\w\s\'"]/', '', strtolower($word)); // Keep single and double quotes
        $tokensWithPositions[] = [
            'token' => $cleanedWord,
            'position' => $originalPosition + 1, // 1-based index
        ];
    }

    return $tokensWithPositions;
}

// Build a positional inverted index and store in the database
function buildPositionalInvertedIndex($directory, $stopwords)
{
    $db = connectDatabase();
    $files = getTextFiles($directory);

    foreach ($files as $index => $file) {
        $filePath = realpath($file);

        // Check if the document is already processed
        $stmt = $db->prepare("SELECT id FROM documents WHERE file_path = :file_path");
        $stmt->execute(['file_path' => $filePath]);
        if ($stmt->fetchColumn()) {
            echo "Skipping already processed file: $filePath\n";
            continue; // Skip processing
        }

        echo "Processing file " . ($index + 1) . " of " . count($files) . ": $filePath\n";

        $documentId = insertDocument($db, $filePath);

        $tokensWithPositions = getFileContentWithPositions($file);

        foreach ($tokensWithPositions as $entry) {
            $token = $entry['token'];

            // Insert position unconditionally
            $termId = insertTerm($db, $token);
            insertPosition($db, $termId, $documentId, $entry['position']);

            // Skip stopwords for term insertion
            if (in_array($token, $stopwords)) {
                continue;
            }
        }
    }
}

// Paths for collection and stoplist
$collectionFilesPath = "data/collection/";
$stoplistFile = "data/stoplist.txt";

// Get stopwords from the stoplist file
$stopwords = getStopwords($stoplistFile);

// Build and store the positional inverted index
buildPositionalInvertedIndex($collectionFilesPath, $stopwords);

echo "Positional inverted index has been stored in the database.\n";

?>