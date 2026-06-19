<?php

test('data backup export includes user rules', function () {
    $source = file_get_contents(app_path('Services/DataExportService.php'));

    expect($source)
        ->toContain('public const SCHEMA_VERSION = 2')
        ->toContain('Rule::withoutGlobalScopes()')
        ->toContain("'rules_count'    => count(\$rulesOut)")
        ->toContain("'rules'       => \$rulesOut");
});

test('data backup email displays rules summary', function () {
    $mail = file_get_contents(app_path('Mail/UserDataBackupMail.php'));
    $view = file_get_contents(resource_path('views/emails/user-data-backup.blade.php'));

    expect($mail)
        ->toContain('cachedRulesCount')
        ->toContain('cachedRules')
        ->toContain("'rules'       => \$this->cachedRules");

    expect($view)
        ->toContain('Rules I follow')
        ->toContain('schema_version: 2')
        ->toContain('@foreach ($rules as $rule)');
});
