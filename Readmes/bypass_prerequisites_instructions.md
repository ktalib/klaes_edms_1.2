# Prerequisites Bypass Instructions

## Quick Development Bypass

To bypass the prerequisites checking for development purposes, you have two options:

### Option 1: Controller Modification (Recommended)

In `app/Http/Controllers/CertificationController.php`, around line 399, change the prerequisite values from `true` to `false` or vice versa:

```php
// Find this section in the getDGData method:
$acknowledgementGenerated = $app->acknowledgement_generated ?? true; // Mock as true for demo
$verificationGenerated = $app->verification_generated ?? true; // Mock as true for demo
$gisCaptured = $app->gis_captured ?? true; // Mock as true for demo
$vettingGenerated = $app->vetting_generated ?? true; // Mock as true for demo
$edmsCaptured = $app->edms_captured ?? true; // Mock as true for demo
$cofoFrontGenerated = $app->cofo_front_generated ?? true; // Mock as true for demo

// Change to:
$bypassPrerequisites = true; // Set to false to require actual prerequisites

$acknowledgementGenerated = $bypassPrerequisites ? true : ($app->acknowledgement_generated ?? false);
$verificationGenerated = $bypassPrerequisites ? true : ($app->verification_generated ?? false);
$gisCaptured = $bypassPrerequisites ? true : ($app->gis_captured ?? false);
$vettingGenerated = $bypassPrerequisites ? true : ($app->vetting_generated ?? false);
$edmsCaptured = $bypassPrerequisites ? true : ($app->edms_captured ?? false);
$cofoFrontGenerated = $bypassPrerequisites ? true : ($app->cofo_front_generated ?? false);
```

### Option 2: URL Parameter

Add `?bypass=true` or `?bypass=false` to the DG list URL:

- Development mode (bypass): `/recertification/dg-data?bypass=true`
- Production mode (require): `/recertification/dg-data?bypass=false`

### Current Status

Currently, all prerequisites are set to `true` by default, which means:
- ✅ All applications show as having complete prerequisites
- ✅ All applications are available for batch processing
- ✅ You can test the approval workflow immediately

### To Test Production Mode

Set `$bypassPrerequisites = false` in the controller to see:
- ❌ Applications with incomplete prerequisites (grayed out)
- ❌ Only applications with all prerequisites can be selected
- ❌ Batch processing only works for complete applications

### Toggle Instructions

1. Open `app/Http/Controllers/CertificationController.php`
2. Find the `getDGData` method (around line 350)
3. Look for the prerequisite section (around line 399)
4. Change `$bypassPrerequisites = true;` to `$bypassPrerequisites = false;`
5. Save the file
6. Refresh the DG's List page

This will immediately switch between development and production modes.