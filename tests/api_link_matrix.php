<?php
// Simple filesystem/link sanity matrix for Malay chat JS -> PHP API endpoints.
// Run: php tests/api_link_matrix.php
// Outputs JSON with presence status and categorization.

header('Content-Type: application/json');

$baseApiDir = realpath(__DIR__ . '/../public/api');
if (!$baseApiDir) {
    echo json_encode(['ok'=>false,'error'=>'api_dir_missing']);
    exit;
}

// Endpoints referenced in MY chat JS flows (parents, siblings, partners, children, token wizard, onboarding wizard)
$endpoints = [
    // Session / generic
    'session_info.php'=>['method'=>'GET','flow'=>'session'],
    'family_tree.php'=>['method'=>'GET','flow'=>'expand'],
    'person_get.php'=>['method'=>'GET','flow'=>'expand'],
    // Expand step APIs
    'expand_start.php'=>['method'=>'POST','flow'=>'expand'],
    'expand_step1_prefill_parents.php'=>['method'=>'GET','flow'=>'expand'],
    'expand_step1_save_parents.php'=>['method'=>'POST','flow'=>'expand'],
    'expand_step2_prefill_siblings.php'=>['method'=>'GET','flow'=>'expand'],
    'expand_step2_save_siblings.php'=>['method'=>'POST','flow'=>'expand'],
    'expand_step3_prefill_partners.php'=>['method'=>'GET','flow'=>'expand'],
    'expand_step3_save_partners.php'=>['method'=>'POST','flow'=>'expand'],
    'expand_step4_save_children.php'=>['method'=>'POST','flow'=>'expand'],
    // Wizard step APIs
    'family_check_name.php'=>['method'=>'POST','flow'=>'wizard'],
    'family_create.php'=>['method'=>'POST','flow'=>'wizard'],
    'step2_self_save.php'=>['method'=>'POST','flow'=>'wizard'],
    'step3_parents_save.php'=>['method'=>'POST','flow'=>'wizard'],
    'step4_siblings_save.php'=>['method'=>'POST','flow'=>'wizard'],
    'step5_partners_save.php'=>['method'=>'POST','flow'=>'wizard'],
    'wizard_complete_no_partners.php'=>['method'=>'POST','flow'=>'wizard'],
    'step6_parents_context.php'=>['method'=>'GET','flow'=>'wizard'],
    'step6_children_save.php'=>['method'=>'POST','flow'=>'wizard'],
    'wizard_complete_step6.php'=>['method'=>'POST','flow'=>'wizard'],
    // Token wizard
    'family_token_validate.php'=>['method'=>'GET','flow'=>'token'],
    'token_family_save_child.php'=>['method'=>'POST','flow'=>'token'],
    'nested_family_create.php'=>['method'=>'POST','flow'=>'token'],
];

$matrix = [];
foreach ($endpoints as $file => $meta) {
    $path = $baseApiDir . DIRECTORY_SEPARATOR . $file;
    $exists = is_file($path);
    $size = $exists ? filesize($path) : 0;
    $matrix[$file] = [
        'exists' => $exists,
        'size' => $size,
        'method' => $meta['method'],
        'flow' => $meta['flow'],
        'path' => $exists ? $path : null
    ];
}

$missing = array_keys(array_filter($matrix, fn($m)=>!$m['exists']));

echo json_encode([
    'ok' => true,
    'api_dir' => $baseApiDir,
    'endpoints_total' => count($endpoints),
    'missing_count' => count($missing),
    'missing' => $missing,
    'matrix' => $matrix
], JSON_PRETTY_PRINT);
?>