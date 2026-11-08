<?php

/**
 * Load the existing contact messages page
 * inside the dashboard content area.
 */

$contactPageOutput = '';

ob_start();

require __DIR__ . '/contact_crud.php';

$contactPageOutput = (string) ob_get_clean();

$contactHeadAssets = '';
$contactBodyContent = $contactPageOutput;

// Extract stylesheets, CSS and scripts.
if (preg_match(
    '/<head\b[^>]*>(.*?)<\/head>/is',
    $contactPageOutput,
    $headMatch
)) {
    if (preg_match_all(
        '/<link\b[^>]*>|<style\b[^>]*>.*?<\/style>|<script\b[^>]*>.*?<\/script>/is',
        $headMatch[1],
        $assetMatches
    )) {
        $contactHeadAssets = implode(
            "\n",
            $assetMatches[0]
        );
    }
}

// Extract the actual page content.
if (preg_match(
    '/<body\b[^>]*>(.*?)<\/body>/is',
    $contactPageOutput,
    $bodyMatch
)) {
    $contactBodyContent = $bodyMatch[1];

} else {

    $contactBodyContent = preg_replace(
        '/<!doctype[^>]*>|<\/?html\b[^>]*>|<\/?head\b[^>]*>|<\/?body\b[^>]*>/i',
        '',
        $contactBodyContent
    );

}

// Display the original page assets and content.
echo $contactHeadAssets;
echo $contactBodyContent;