#!/usr/bin/env python3
"""
Convert Deeds Data.csv to SQL INSERT statements for pra table
- Normalizes dates to YYYY-MM-DD format
- Cleans file numbers (removes TEMP markers, extra spaces)
- Places file_number into mlsFNo column
- Handles incomplete data gracefully
"""

import csv
import re
from datetime import datetime
import os

def normalize_date(date_str):
    """Convert various date formats to SQL-compatible format"""
    if not date_str or date_str.strip() == '':
        return 'NULL'
    
    # Common date format issues in the CSV: M/D/YYYY or D/M/YYYY
    try:
        # Try parsing M/D/YYYY format (American)
        dt = datetime.strptime(date_str.strip(), '%m/%d/%Y')
        return f"'{dt.strftime('%Y-%m-%d')}'"
    except:
        pass
    
    try:
        # Try parsing D/M/YYYY format (European)
        dt = datetime.strptime(date_str.strip(), '%d/%m/%Y')
        return f"'{dt.strftime('%Y-%m-%d')}'"
    except:
        pass
    
    print(f"Warning: Could not parse date '{date_str}', using NULL")
    return 'NULL'

def escape_sql(value):
    """Escape single quotes for SQL and handle NULL values"""
    if value is None or value == '' or str(value).strip() == '':
        return 'NULL'
    return "'" + str(value).replace("'", "''").strip() + "'"

def normalize_file_number(file_num):
    """Clean up file numbers - remove TEMP markers, extra spaces"""
    if not file_num or file_num.strip() == '':
        return 'NULL'
    
    # Remove (TEMP) suffix
    file_num = re.sub(r'\s*\(TEMP\)\s*', '', file_num.strip())
    # Remove extra spaces and hyphens
    file_num = re.sub(r'\s+', ' ', file_num.strip())
    # Fix double hyphens
    file_num = re.sub(r'--+', '-', file_num)
    
    return escape_sql(file_num)

def main():
    input_file = r'c:\xampp\htdocs\klaes\docs\Deeds Data.csv'
    output_file = r'c:\xampp\htdocs\klaes\database_scripts\insert_pra_from_deeds_data.sql'
    
    if not os.path.exists(input_file):
        print(f"Error: Input file not found: {input_file}")
        return
    
    sql_statements = []
    sql_statements.append('-- PRA Table Inserts from Deeds Data.csv')
    sql_statements.append(f"-- Generated on: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    sql_statements.append('-- Note: Dates normalized to YYYY-MM-DD, file numbers cleaned, TEMP markers removed')
    sql_statements.append('-- File numbers are placed in mlsFNo column\n')
    sql_statements.append('SET IDENTITY_INSERT pra ON;')
    sql_statements.append('GO\n')
    
    skipped = 0
    processed = 0
    
    try:
        with open(input_file, 'r', encoding='utf-8-sig') as f:
            reader = csv.DictReader(f)
            
            for idx, row in enumerate(reader, 1):
                # Extract and normalize fields
                trans_date = normalize_date(row.get('transaction_date', '').strip())
                serial = escape_sql(row.get('serialNo', '').strip())
                
                # Handle duplicate pageNo column in header
                page = escape_sql(row.get('pageNo', '').strip())
                
                # Volume might be the third column (also labeled pageNo in header)
                volume_raw = ''
                if 'volumeNo' in row:
                    volume_raw = row.get('volumeNo', '').strip()
                else:
                    # Try to get from the third numeric column
                    for key in row.keys():
                        if 'pageNo' in key or 'volume' in key.lower():
                            if volume_raw == '':
                                volume_raw = row.get(key, '').strip()
                
                volume = escape_sql(volume_raw if volume_raw else row.get('pageNo', '').strip())
                
                reg_no = escape_sql(row.get('regNo', '').strip())
                
                # Handle instrument_type with possible leading spaces
                instrument = escape_sql(
                    row.get('  instrument_type', '').strip() or 
                    row.get('instrument_type', '').strip() or
                    row.get(' instrument_type', '').strip()
                )
                
                file_num = normalize_file_number(row.get('file_number', '').strip())
                
                # Handle related_file_number with possible leading spaces
                related = normalize_file_number(
                    row.get(' related_file_number', '').strip() or 
                    row.get('related_file_number', '').strip()
                )
                
                grantor = escape_sql(row.get('Grantor', '').strip())
                grantee = escape_sql(row.get('Grantee', '').strip())
                
                # Skip rows with missing critical data
                if file_num == 'NULL':
                    skipped += 1
                    print(f"Row {idx}: Skipped - missing file_number")
                    continue
                
                if grantor == 'NULL' and grantee == 'NULL':
                    skipped += 1
                    print(f"Row {idx}: Skipped - missing both Grantor and Grantee")
                    continue
                
                # Generate INSERT statement
                sql = f"""INSERT INTO pra (mlsFNo, transaction_date, transaction_type, instrument_type, serialNo, pageNo, volumeNo, regNo, Grantor, Grantee, related_file_number, created_at, updated_at)
VALUES ({file_num}, {trans_date}, {instrument}, {instrument}, {serial}, {page}, {volume}, {reg_no}, {grantor}, {grantee}, {related}, GETDATE(), GETDATE());"""
                
                sql_statements.append(sql)
                processed += 1
                
                # Add batch separator every 100 rows for better execution
                if processed % 100 == 0:
                    sql_statements.append('GO\n')
                    print(f"Processed {processed} records...")
        
        # Final batch separator and cleanup
        sql_statements.append('\nGO')
        sql_statements.append('SET IDENTITY_INSERT pra OFF;')
        sql_statements.append('GO')
        
        # Write to output file
        with open(output_file, 'w', encoding='utf-8') as out:
            out.write('\n'.join(sql_statements))
        
        print(f"\n{'='*60}")
        print(f"✓ SQL script generated successfully!")
        print(f"{'='*60}")
        print(f"Output file: {output_file}")
        print(f"Total records in CSV: {idx}")
        print(f"Records processed: {processed}")
        print(f"Records skipped: {skipped}")
        print(f"\nYou can now execute this script in SQL Server Management Studio")
        print(f"or via sqlcmd to import the data into the pra table.")
        
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()

if __name__ == '__main__':
    main()
