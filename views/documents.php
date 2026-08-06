<div class="card">
    <div class="card-header">
        <h2 class="card-title">Documents in <?php echo Security::escape($collection); ?></h2>
        <p class="card-subtitle">Database: <?php echo Security::escape($database); ?></p>
    </div>

    <div class="card-body">
        <?php if (empty($rows)) { ?>
            <div class="alert alert-info">
                No documents found in this collection.
            </div>
        <?php } else { ?>
            <?php
            // Determine the toggle sort order
            $toggleSort = ($sortOrder ?? 'desc') === 'desc' ? 'asc' : 'desc';
            $sortIcon = ($sortOrder ?? 'desc') === 'desc' ? '↓' : '↑';
            $sortUrl = View::url(['action' => 'documents', 'db' => $database, 'collection' => $collection, 'sort' => $toggleSort]);
            ?>
            <table class="table table-documents">
                <thead>
                    <tr>
                        <th style="width: 240px;">
                            <a href="<?php echo Security::escape($sortUrl); ?>" style="color: inherit; text-decoration: none;">
                                _id <?php echo $sortIcon; ?>
                            </a>
                        </th>
                        <th>Preview</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $doc = $row['document'];
                        $preview = $doc;
                        unset($preview['_id']);
                        ?>
                        <tr>
                            <td><code><?php echo Security::escape(View::documentId($doc['_id'] ?? null)); ?></code></td>
                            <td><?php echo Security::escape(View::truncate(json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160)); ?></td>
                            <td>
                                <a href="?action=document&db=<?php echo urlencode($database); ?>&collection=<?php echo urlencode($collection); ?>&id=<?php echo urlencode($row['id']); ?>"
                                   class="btn btn-sm btn-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($paginator->getTotalPages() > 1) { ?>
                <div class="pagination-container">
                    <?php
                    echo $paginator->render('', [
                        'action' => 'documents',
                        'db' => $database,
                        'collection' => $collection,
                        'sort' => $sortOrder ?? 'desc',
                    ]);
                    ?>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
