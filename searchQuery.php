<?php
include_once('db.php');

function getStopwords($stoplistFile)
{
    $stopwords = [];
    if (file_exists($stoplistFile)) {
        $stopwords = array_map('trim', file($stoplistFile, FILE_IGNORE_NEW_LINES));
    }
    return array_map('strtolower', $stopwords);
}

function removeStopwords($text, $stopwords)
{
    $text = preg_replace('/[^\w\s]+/', '', $text); // Keep words, whitespace, and apostrophes
    $words = preg_split('/\s+/', $text); // Split by whitespace
    $filteredWords = array_filter($words, function ($word) use ($stopwords) {
        return !in_array(strtolower($word), $stopwords);
    });
    return implode(' ', $filteredWords);
}

function lemmatizeWithPython($text)
{
    $inputFile = tempnam(sys_get_temp_dir(), 'input_');
    $outputFile = tempnam(sys_get_temp_dir(), 'output_');

    file_put_contents($inputFile, $text);

    $command = escapeshellcmd("python3 lemmatizer.py $inputFile $outputFile");
    shell_exec($command);

    if (!file_exists($outputFile)) {
        unlink($inputFile);
        return [];
    }

    $lemmas = file_get_contents($outputFile);

    unlink($inputFile);
    unlink($outputFile);

    $decoded = json_decode($lemmas, true);
    return $decoded ? array_map('strtolower', $decoded) : [];
}

function searchSingleTerm($db, $term)
{
    // Query to fetch documents containing the term with positions and frequencies
    $stmt = $db->prepare("
        SELECT 
            d.file_path AS document, 
            p.position, 
            COUNT(p.id) AS frequency
        FROM 
            terms t
        JOIN 
            positions p ON t.id = p.term_id
        JOIN 
            documents d ON p.document_id = d.id
        WHERE 
            t.term = ?
        GROUP BY 
            d.file_path, p.position
        ORDER BY 
            frequency DESC, d.file_path, p.position;
    ");
    $stmt->execute([$term]);

    // Process the results
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $document = $row['document'];
        if (!isset($results[$document])) {
            $results[$document] = [
                'terms' => [],
                'score' => 0
            ];
        }

        $results[$document]['terms'][] = [
            'term' => $term,
            'position' => $row['position']
        ];
        $results[$document]['score'] += $row['frequency'];
    }

    uasort($results, function ($a, $b) {
        return $b['score'] <=> $a['score']; // Sort by score descending
    });

    return $results;
}


function searchPhrase($db, $lemmas)
{
    // Fetch all positions of the query terms
    $stmt = $db->prepare("
        SELECT d.file_path AS document, t.term, p.position
        FROM terms t
        JOIN positions p ON t.id = p.term_id
        JOIN documents d ON p.document_id = d.id
        WHERE t.term IN (" . implode(',', array_fill(0, count($lemmas), '?')) . ")
        ORDER BY d.file_path, p.position;
    ");
    $stmt->execute($lemmas);

    $rawResults = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rawResults[$row['document']][] = [
            'term' => $row['term'],
            'position' => $row['position']
        ];
    }

    $results = [];
    foreach ($rawResults as $document => $termsData) {
        $foundTerms = array_column($termsData, 'term');
        $positions = array_column($termsData, 'position');
        sort($positions);

        // Ensure the document contains all query terms
        if (array_diff($lemmas, $foundTerms) === []) {
            $isConsecutive = true;
            for ($i = 1; $i < count($positions); $i++) {
                if ($positions[$i] !== $positions[$i - 1] + 1) {
                    $isConsecutive = false;
                    break;
                }
            }

            $results[$document] = [
                'is_consecutive' => $isConsecutive,
                'terms' => $termsData,
                'score' => count($termsData) // Score by number of matched terms
            ];
        }
    }

    uasort($results, function ($a, $b) {
        if ($a['is_consecutive'] === $b['is_consecutive']) {
            return $b['score'] <=> $a['score']; // Sort by score descending
        }
        return $b['is_consecutive'] <=> $a['is_consecutive']; // Prioritize consecutive matches
    });

    return $results;
}

function searchQuery($query, $stoplistFile)
{
    $db = connectDatabase();

    $stopwords = getStopwords($stoplistFile);
    $query = removeStopwords(strtolower($query), $stopwords);
    $lemmas = lemmatizeWithPython($query);

    if (empty($lemmas)) {
        return [];
    }

    if (count($lemmas) > 1) {
        return searchPhrase($db, $lemmas);
    } else {
        return searchSingleTerm($db, $lemmas[0]);
    }
}

function extractContext($documentPath, $positions, $terms, $contextSize = 5)
{
    $content = file_get_contents($documentPath);
    $words = preg_split('/\s+/', $content);
    $contexts = [];

    foreach ($positions as $index => $position) {
        $term = $terms[$index];
        $start = max(0, $position - 1 - $contextSize);
        $end = min(count($words), $position - 1 + $contextSize + 1);

        $context = array_slice($words, $start, $end - $start);
        $highlightedTerm = "<span class='text-decoration-underline fw-bold text-danger text-uppercase'>" . htmlspecialchars($term) . "</span>";
        $context[$position - 1 - $start] = $highlightedTerm;
        $contexts[] = implode(' ', $context);
    }

    return implode(' ... ', $contexts);
}
?>