import sys
import spacy
import json

# Load the medium-sized Spacy language model
try:
    nlp = spacy.load("en_core_web_md")
except Exception as e:
    with open(sys.argv[2], "w") as f:
        f.write(json.dumps({"error": f"Spacy model load error: {str(e)}"}))
    sys.exit(1)

# Ensure correct arguments
if len(sys.argv) != 3:
    print("Usage: python3 lemmatizer.py <input_file> <output_file>")
    sys.exit(1)

input_file = sys.argv[1]
output_file = sys.argv[2]

try:
    # Step 1: Read input data from the file
    with open(input_file, "r") as f:
        text = f.read()

    # Step 2: Process the text
    doc = nlp(text)
    lemmas = [token.lemma_ for token in doc]

    # Step 3: Write the processed data to the output file
    with open(output_file, "w") as f:
        f.write(json.dumps(lemmas))

except Exception as e:
    with open(output_file, "w") as f:
        f.write(json.dumps({"error": f"Error processing text: {str(e)}"}))
    sys.exit(1)
