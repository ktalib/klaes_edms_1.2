<?php
/**
 * Test script to verify commissioning status fix
 * 
 * This script tests the new API endpoint to ensure it returns correct commissioning status
 */

// Get the application ID from RES-1982-2081
$parentFileNo = 'RES-1982-2081';

// Connect to database
$dsn = "sqlsrv:Server=localhost;Database=klaes";
$username = "sa";
$password = "your_password"; // Update with your actual password

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Testing commissioning status for parent file: $parentFileNo\n\n";
    
    // Test 1: Check st_file_numbers table directly
    echo "=== Test 1: Direct st_file_numbers query ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            fileno,
            np_fileno,
            first_name,
            surname,
            corporate_name,
            applicant_type,
            status,
            date_commissioned,
            used_at,
            CASE 
                WHEN date_commissioned IS NOT NULL THEN 1
                WHEN used_at IS NOT NULL AND status = 'USED' THEN 1
                ELSE 0
            END as is_commissioned
        FROM st_file_numbers 
        WHERE np_fileno = ? 
        AND file_no_type IN ('PUA', 'SUA')
        ORDER BY unit_sequence
    ");
    $stmt->execute([$parentFileNo]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($units as $unit) {
        $applicantName = $unit['applicant_type'] === 'Corporate' 
            ? $unit['corporate_name'] 
            : trim($unit['first_name'] . ' ' . $unit['surname']);
        
        $commissionStatus = $unit['is_commissioned'] ? 'Commissioned' : 'Not Commissioned';
        
        echo "Unit: {$unit['fileno']}\n";
        echo "  Applicant: $applicantName\n";
        echo "  Status: {$unit['status']}\n";
        echo "  Date Commissioned: " . ($unit['date_commissioned'] ?: 'NULL') . "\n";
        echo "  Used At: " . ($unit['used_at'] ?: 'NULL') . "\n";
        echo "  Is Commissioned: $commissionStatus\n\n";
    }
    
    // Test 2: Check if there's a mother_application_id
    echo "=== Test 2: Find mother application ===\n";
    $stmt = $pdo->prepare("SELECT DISTINCT mother_application_id FROM st_file_numbers WHERE np_fileno = ?");
    $stmt->execute([$parentFileNo]);
    $appId = $stmt->fetchColumn();
    
    if ($appId) {
        echo "Found mother application ID: $appId\n\n";
        
        // Test 3: Check buyer_list table
        echo "=== Test 3: Check buyer_list for application $appId ===\n";
        $stmt = $pdo->prepare("
            SELECT 
                bl.id,
                bl.buyer_title,
                bl.buyer_name,
                bl.unit_no,
                sfn.fileno as unit_file_no,
                sfn.status as file_status,
                sfn.date_commissioned,
                sfn.used_at,
                CASE 
                    WHEN sfn.date_commissioned IS NOT NULL THEN 1
                    WHEN sfn.used_at IS NOT NULL AND sfn.status = 'USED' THEN 1
                    ELSE 0
                END as is_commissioned
            FROM buyer_list bl
            LEFT JOIN st_file_numbers sfn ON sfn.buyer_list_id = bl.id AND sfn.file_no_type = 'PUA'
            WHERE bl.application_id = ?
            ORDER BY bl.created_at DESC
        ");
        $stmt->execute([$appId]);
        $buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($buyers as $buyer) {
            $commissionStatus = $buyer['is_commissioned'] ? 'Commissioned' : 'Not Commissioned';
            
            echo "Buyer ID: {$buyer['id']}\n";
            echo "  Name: {$buyer['buyer_title']} {$buyer['buyer_name']}\n";
            echo "  Unit: {$buyer['unit_no']}\n";
            echo "  Unit File No: " . ($buyer['unit_file_no'] ?: 'NULL') . "\n";
            echo "  Status: " . ($buyer['file_status'] ?: 'NULL') . "\n";
            echo "  Is Commissioned: $commissionStatus\n\n";
        }
    } else {
        echo "No mother application found for $parentFileNo\n";
    }
    
    echo "Test completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>