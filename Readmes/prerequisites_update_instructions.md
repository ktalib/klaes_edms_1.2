# Prerequisites Database Integration Instructions

## Updated Controller Code

Replace the prerequisite checking section in `app/Http/Controllers/CertificationController.php` around line 397-404 with this code:

```php
// Development bypass setting - can be controlled via query parameter
$bypassPrerequisites = $request->get('bypass', 'true') === 'true'; // Default to true for development

if ($bypassPrerequisites) {
    // Development mode - all prerequisites are automatically complete
    $acknowledgementGenerated = true;
    $verificationGenerated = true;
    $gisCaptured = true;
    $vettingGenerated = true;
    $edmsCaptured = true;
    $cofoFrontGenerated = true;
} else {
    // Production mode - check actual database values
    $fileNo = $app->file_number ?? null;
    
    // 1. Check certificate_generated (CofO Front Page)
    $cofoFrontGenerated = ($app->certificate_generated ?? 0) == 1;
    
    // 2. Check acknowledgement
    $acknowledgementGenerated = ($app->acknowledgement ?? '') === 'Generated';
    
    // 3. Check verification status
    $verificationGenerated = ($app->verification ?? '') === 'Verified';
    
    // 4. Check EDMS captured (file_indexings + pagetypings)
    $edmsCaptured = false;
    try {
        $fileIndexing = DB::connection('sqlsrv')->table('file_indexings')
            ->where('recertification_application_id', $app->id)
            ->first();
            
        if ($fileIndexing) {
            $edmsCaptured = DB::connection('sqlsrv')->table('pagetypings')
                ->where('file_indexing_id', $fileIndexing->id)
                ->exists();
        }
    } catch (\Throwable $e) {
        $edmsCaptured = false;
    }
    
    // 5. Check GIS captured
    $gisCaptured = ($app->gis_captured ?? 0) == 1;
    
    // 6. Check vetting generated
    $vettingGenerated = ($app->vetting_generated ?? 0) == 1;
    
    // 7. Check CofO exists in Cofo table (additional check)
    $cofoExists = false;
    if ($fileNo) {
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('Cofo')) {
                $cofoExists = DB::connection('sqlsrv')->table('Cofo')
                    ->where('fileNo', $fileNo)
                    ->orWhere('mlsFNo', $fileNo)
                    ->orWhere('kangisFileNo', $fileNo)
                    ->orWhere('NewKANGISFileno', $fileNo)
                    ->exists();
            }
        } catch (\Throwable $e) {
            $cofoExists = false;
        }
    }
}
```

## What to Replace

Find this section in the `getDGData` method:

```php
// Mock prerequisite data (since columns don't exist yet)
// In production these would come from actual database columns
$acknowledgementGenerated = $app->acknowledgement_generated ?? true; // Mock as true for demo
$verificationGenerated = $app->verification_generated ?? true; // Mock as true for demo
$gisCaptured = $app->gis_captured ?? true; // Mock as true for demo
$vettingGenerated = $app->vetting_generated ?? true; // Mock as true for demo
$edmsCaptured = $app->edms_captured ?? true; // Mock as true for demo
$cofoFrontGenerated = $app->cofo_front_generated ?? true; // Mock as true for demo
```

Replace it with the code above.

## How to Use

### Development Mode (Default)
- URL: `/recertification/dg-data` or `/recertification/dg-data?bypass=true`
- All prerequisites show as complete
- All applications available for batch processing

### Production Mode
- URL: `/recertification/dg-data?bypass=false`
- Real database checks are performed
- Only applications with actual completed prerequisites are available

## Database Fields Checked

1. **Certificate Generated**: `certificate_generated = 1`
2. **Acknowledgement**: `acknowledgement = 'Generated'`
3. **Verification**: `verification = 'Verified'`
4. **EDMS Captured**: Records exist in `file_indexings` and `pagetypings` tables
5. **GIS Captured**: `gis_captured = 1`
6. **Vetting Generated**: `vetting_generated = 1`
7. **CofO Exists**: Record exists in `Cofo` table with matching file number

## Testing

1. **Development Testing**: Use default URL to see all applications as ready
2. **Production Testing**: Add `?bypass=false` to see realistic prerequisite checking
3. **Mixed Testing**: Switch between modes by changing the URL parameter

This gives you full control over the prerequisite checking without needing to modify code each time.