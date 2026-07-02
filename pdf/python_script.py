import camelot
import pandas as pd
import re
# import os

# print("Current working directory:", os.getcwd())
# print("Files in folder:", os.listdir())
# exit()
PDF_FILE = r"C:\xampp\htdocs\Dddcet\pdf\Cut off 2025.pdf"

# Extract tables from all pages
tables = camelot.read_pdf(
    PDF_FILE,
    pages="all",
    flavor="lattice"   # table with borders
)

all_rows = []

for table in tables:
    df = table.df

    # Remove header rows repeated on each page
    for _, row in df.iterrows():
        values = [str(x).strip() for x in row.tolist()]

        # Skip invalid/header rows
        if len(values) < 10:
            continue

        # Try detecting actual data row
        if not values[0].isdigit():
            continue

        # Expected PDF structure:
        # SrNo | Institute | InstType | Branch | Quota | AdmissionCategory | FirstMarks | FirstRank | LastMarks | LastRank

        cleaned = {
            "Sr No": values[0],
            "Name of Institute": values[1],
            "Institute Type": values[2],
            "Branch": values[3],
            "Admission Category": values[5],
            "Quota": values[4],
            "First Admitted DDCET Marks": values[6],
            "First Admitted DDCET Rank": values[7],
            "Last Admitted DDCET Marks": values[8],
            "Last Admitted DDCET Rank": values[9]
        }

        all_rows.append(cleaned)

# Create dataframe
final_df = pd.DataFrame(all_rows)

# Save CSV
final_df.to_csv("ddcet_full_data_2025.csv", index=False)

print("Done! CSV saved as ddcet_full_data_2025.csv")
print("Total rows extracted:", len(final_df))