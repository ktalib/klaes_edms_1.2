<?php
resources/views/components/global-fileno-modal.blade.php = 'resources/views/components/global-fileno-modal.blade.php';
 = file_get_contents(resources/views/components/global-fileno-modal.blade.php);

// Inject MLS details
 = preg_replace(
    '/(<div\s+id="mls-copy-btn".*?<\/button>\s*<\/div>\s*<\/div>)\s*(<\/div>\s*<!-- Temporary File Number UI -->)/s',
    '\' . "\n                        <div id=\"mls-details-container\"></div>\n                    \",
    
);

// Inject KANGIS details
 = preg_replace(
    '/(<div\s+id="kangis-copy-btn".*?<\/button>\s*<\/div>\s*<\/div>)\s*(<\/div>\s*<\/div>\s*<!-- New KANGIS Tab Content -->)/s',
    '\' . "\n                        <div id=\"kangis-details-container\"></div>\n                    \",
    
);

// Inject New KANGIS details
 = preg_replace(
    '/(<div\s+id="newkangis-copy-btn".*?<\/button>\s*<\/div>\s*<\/div>)\s*(<\/div>\s*<\/div>\s*<!-- End Tab Content Container -->)/s',
    '\' . "\n                        <div id=\"newkangis-details-container\"></div>\n                    \",
    
);

file_put_contents(resources/views/components/global-fileno-modal.blade.php, );
echo "Injected details containers.\n";
