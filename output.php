<?php
// Function to print terms and their occurrences grouped by term

include_once('db.php');
function printDatabaseContents()
{
    $db = connectDatabase();

    // Query to get terms, documents, and positions
    $query = "
        SELECT 
            t.term AS term,
            d.file_path AS document,
            GROUP_CONCAT(p.position ORDER BY p.position ASC) AS positions
        FROM 
            terms t
        JOIN 
            positions p ON t.id = p.term_id
        JOIN 
            documents d ON p.document_id = d.id
        GROUP BY 
            t.term, d.file_path
        ORDER BY 
            t.term, d.file_path;
    ";

    $stmt = $db->query($query);

    // Fetch and organize results by term
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $term = $row['term'];
        $document = $row['document'];
        $positions = $row['positions'];

        if (!isset($results[$term])) {
            $results[$term] = [];
        }
        $results[$term][] = [
            'document' => $document,
            'positions' => $positions,
        ];
    }

    // Display the results grouped by term
    echo "<pre>";
    echo "Terms and their occurrences in documents:\n";
    foreach ($results as $term => $documents) {
        echo "Term: $term\n";
        foreach ($documents as $doc) {
            echo "  Document: " . $doc['document'] . "\n";
            echo "  Positions: " . $doc['positions'] . "\n";
        }
        echo "--------------------------\n";
    }
    echo "</pre>";
}

// Call the function to print the database contents
printDatabaseContents();
?>