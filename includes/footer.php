    <!-- JavaScript -->
    <script src="<?= assetUrl('assets/js/app.js') ?>"></script>
    
    <?php
    // Include any page-specific JavaScript
    if (isset($additionalJs)) {
        foreach ((array)$additionalJs as $jsFile) {
            echo "<script src=\"{$jsFile}\"></script>\n";
        }
    }
    
    // Include any inline JavaScript
    if (isset($inlineJs)) {
        echo "<script>{$inlineJs}</script>\n";
    }
    ?>
</body>
</html>