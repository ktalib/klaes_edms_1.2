import re

with open('app/Http/Controllers/FilearchiveController.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Add getModuleTheme
theme_code = '''
    private function getModuleTheme(string $module): ?array
    {
        $normalized = strtolower(trim($module));
        $themes = [
            'kangis' => [
                'title' => 'KANGIS',
                'bg' => 'bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600',
                'icon' => 'map',
                'text_muted' => 'text-yellow-100',
            ],
            'sltr' => [
                'title' => 'SLTR',
                'bg' => 'bg-gradient-to-r from-blue-600 via-blue-500 to-blue-700',
                'icon' => 'book-open',
                'text_muted' => 'text-blue-100',
            ],
            'dciv' => [
                'title' => 'DCIV',
                'bg' => 'bg-gradient-to-r from-purple-600 via-purple-500 to-purple-700',
                'icon' => 'shield',
                'text_muted' => 'text-purple-100',
            ],
            'cadastral' => [
                'title' => 'CADASTRAL',
                'bg' => 'bg-gradient-to-r from-emerald-600 via-emerald-500 to-emerald-700',
                'icon' => 'layers',
                'text_muted' => 'text-emerald-100',
            ],
        ];
        return $themes[$normalized] ?? null;
    }
'''

content = re.sub(r'(class FilearchiveController extends Controller\s*\{\s*)', lambda m: m.group(1) + theme_code, content)

# Inject to $moduleTheme mapping in index
content = re.sub(r'(\$module = \$request->get\(\'url\', \'\'\);)', 
    r"\1\n        $moduleTheme = $this->getModuleTheme($module);\n        if ($moduleTheme) {\n            $PageTitle = $moduleTheme['title'] . ' Digital Archive';\n        }\n", content)

# update compact block in index
content = re.sub(r'compact\([\s\S]*?\'yearOptions\',\s*\'registryOptions\'\s*\)\);',
    lambda m: m.group(0).replace("'module',", "'module',\n            'moduleTheme',"), content)

with open('app/Http/Controllers/FilearchiveController.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated controller")
