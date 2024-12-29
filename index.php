<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
            padding-top: 50px;
        }

        .search-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .search-container form {
            display: flex;
            justify-content: space-between;
        }

        .search-input {
            flex: 1;
            margin-right: 10px;
        }

        .btn-search {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background-color: #0056b3;
        }

        .result-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-bottom: 20px;
        }

        .result-title {
            font-weight: bold;
            color: #007bff;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .highlight {
            background-color: #ffd54f;
            padding: 2px 4px;
            border-radius: 3px;
            color: #000;
        }

        .result-score {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .result-content p {
            margin-bottom: 5px;
        }

        .no-results {
            text-align: center;
            color: #6c757d;
            margin-top: 30px;
            font-size: 1.1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Search Bar -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="search-container">
                    <form method="POST" action="">
                        <input type="text" name="query" class="form-control search-input"
                            placeholder="Search for a word or phrase..." required>
                        <button type="submit" class="btn btn-search">Search</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    include_once('searchQuery.php');
                    $userQuery = $_POST['query'];
                    $stoplistFile = "data/stoplist.txt";

                    // Fetch search results
                    $results = searchQuery($userQuery, $stoplistFile);

                    // Limit results to 5 documents
                    $results = array_slice($results, 0, 5, true);

                    if (!empty($results)) {
                        foreach ($results as $document => $data) {
                            echo "<div class='result-container'>";
                            echo "<div class='result-title'>" . htmlspecialchars($document) . "</div>";

                            if ($data['is_consecutive']) {
                                $positions = array_column($data['terms'], 'position');
                                $terms = array_column($data['terms'], 'term');
                                $context = extractContext($document, $positions, $terms);
                                echo "<p><strong>Context:</strong> $context</p>";
                            } else {
                                foreach ($data['terms'] as $termData) {
                                    $context = extractContext($document, [$termData['position']], [$termData['term']]);
                                    echo "<p><strong>Term:</strong> " . htmlspecialchars($termData['term']) . "</p>";
                                    echo "<p><strong>position:</strong> " . htmlspecialchars($termData['position']) . "</p>";
                                    echo "<p><strong>Context:</strong> $context</p>";
                                }
                            }

                            echo "<p class='result-score'><strong>Score:</strong> " . htmlspecialchars($data['score']) . "</p>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='no-results'>No results found for your query.</p>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>