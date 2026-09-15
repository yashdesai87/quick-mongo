<div class="card">
    <div class="card-header">
        <h2 class="card-title">Document Details</h2>
        <p class="card-subtitle">
            Collection: <?php echo Security::escape($collection); ?> |
            Database: <?php echo Security::escape($database); ?>
        </p>
    </div>

    <div class="card-body">
        <div class="document-info">
            <div class="info-row">
                <strong>Document ID:</strong>
                <code class="doc-id-display"><?php echo Security::escape($documentId); ?></code>
            </div>
            <?php if (substr($collection, -6) === '.files') { ?>
                <div class="info-row">
                    <strong>GridFS file:</strong>
                    <code><?php echo Security::escape($document->filename ?? '(no filename)'); ?></code>
                    | <?php echo View::formatBytes($document->length ?? 0); ?>
                    | uploaded <?php echo View::time($document->uploadDate ?? null); ?>
                    <a href="?action=download&db=<?php echo urlencode($database); ?>&collection=<?php echo urlencode($collection); ?>&id=<?php echo urlencode($idParam); ?>"
                       class="btn btn-sm btn-secondary">Download</a>
                </div>
            <?php } ?>
        </div>

        <div class="document-content">
            <h3 class="section-title">Document Data</h3>

            <div class="json-viewer">
                <pre class="language-json"><code><?php
                    // Format JSON for display
                    $jsonString = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo Security::escape($jsonString);
            ?></code></pre>
            </div>

            <!-- Tree View -->
            <div class="tree-view" id="document-tree">
                <?php
            function renderJsonTree($data, $level = 0)
            {
                if (is_array($data) || is_object($data)) {
                    $isIndexed = is_array($data) && array_keys($data) === range(0, count($data) - 1);

                    echo '<ul class="json-tree'.($level === 0 ? ' root' : '').'">';

                    foreach ($data as $key => $value) {
                        echo '<li>';

                        if (is_array($value) || is_object($value)) {
                            $count = is_array($value) ? count($value) : count((array) $value);
                            $type = is_array($value) ? 'Array' : 'Object';

                            echo '<span class="json-toggle expanded">';
                            echo '<span class="json-key">'.Security::escape($key).'</span>';
                            echo '<span class="json-type">'.$type.'['.$count.']</span>';
                            echo '</span>';

                            echo '<div class="json-nested">';
                            renderJsonTree($value, $level + 1);
                            echo '</div>';
                        } else {
                            echo '<span class="json-item">';
                            if (! $isIndexed) {
                                echo '<span class="json-key">'.Security::escape($key).':</span> ';
                            }

                            if (is_bool($value)) {
                                echo '<span class="json-value json-boolean">'.($value ? 'true' : 'false').'</span>';
                            } elseif ($value === null) {
                                echo '<span class="json-value json-null">null</span>';
                            } elseif (is_numeric($value)) {
                                echo '<span class="json-value json-number">'.Security::escape($value).'</span>';
                            } else {
                                echo '<span class="json-value json-string">"'.Security::escape($value).'"</span>';
                            }
                            echo '</span>';
                        }

                        echo '</li>';
                    }

                    echo '</ul>';
                }
            }

            renderJsonTree($document);
            ?>
            </div>
        </div>

        <div class="document-actions">
            <a href="?action=documents&db=<?php echo urlencode($database); ?>&collection=<?php echo urlencode($collection); ?>"
               class="btn btn-secondary">
                ← Back to Documents
            </a>
        </div>
    </div>
</div>
