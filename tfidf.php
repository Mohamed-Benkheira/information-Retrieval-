<?php
// tfidf.php

function calculateTFIDF($documents)
{
    $termFrequency = [];
    $documentFrequency = [];
    $numDocuments = count($documents);

    foreach ($documents as $docId => $document) {
        $words = explode(" ", strtolower($document));
        $wordCounts = array_count_values($words);
        $termFrequency[$docId] = $wordCounts;

        foreach ($wordCounts as $word => $count) {
            $documentFrequency[$word] = ($documentFrequency[$word] ?? 0) + 1;
        }
    }

    $tfidf = [];
    foreach ($termFrequency as $docId => $wordCounts) {
        $tfidf[$docId] = [];
        foreach ($wordCounts as $word => $count) {
            $tf = $count / array_sum($wordCounts); // Term Frequency
            $idf = log($numDocuments / ($documentFrequency[$word])); // Inverse Document Frequency
            $tfidf[$docId][$word] = $tf * $idf;
        }
    }

    return [$tfidf, $documentFrequency];
}

function vectorizeQuery($query, $documentFrequency, $numDocuments)
{
    $queryWords = explode(" ", strtolower($query));
    $queryVector = [];

    foreach ($queryWords as $word) {
        $tf = 1 / count($queryWords); // Term Frequency for query
        $idf = isset($documentFrequency[$word]) ? log($numDocuments / $documentFrequency[$word]) : 0;
        $queryVector[$word] = $tf * $idf;
    }

    return $queryVector;
}

function cosineSimilarity($vector1, $vector2)
{
    $dotProduct = 0;
    $magnitude1 = 0;
    $magnitude2 = 0;

    $allWords = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));

    foreach ($allWords as $word) {
        $value1 = $vector1[$word] ?? 0;
        $value2 = $vector2[$word] ?? 0;

        $dotProduct += $value1 * $value2;
        $magnitude1 += $value1 ** 2;
        $magnitude2 += $value2 ** 2;
    }

    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0;
    }

    return $dotProduct / (sqrt($magnitude1) * sqrt($magnitude2));
}
