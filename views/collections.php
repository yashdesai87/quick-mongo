<div class="card">
    <div class="card-header">
        <h2 class="card-title">Collections in <?php echo Security::escape($database); ?></h2>
        <p class="card-subtitle">Select a collection to view its documents</p>
    </div>

    <div class="card-body">
        <?php if (empty($collections)) { ?>
            <div class="alert alert-info">
                No collections found in this database.
            </div>
        <?php } else { ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Collection Name</th>
                        <th>Document Count</th>
                        <th>Size</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $collection) { ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?action=documents&db=<?php echo urlencode($database); ?>&collection=<?php echo urlencode($collection['name']); ?>"
                                       class="collection-link">
                                        <?php echo Security::escape($collection['name']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo number_format($collection['count']); ?> <?php echo $collection['count'] == 1 ? 'document' : 'documents'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $collection['size'] !== null ? View::formatBytes($collection['size']) : '-'; ?>
                            </td>
                            <td>
                                <?php echo Security::escape($collection['type']); ?>
                            </td>
                            <td>
                                <a href="?action=documents&db=<?php echo urlencode($database); ?>&collection=<?php echo urlencode($collection['name']); ?>"
                                   class="btn btn-sm btn-primary">
                                    View Documents
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="table-footer">
                <p class="text-muted">
                    Total collections: <?php echo count($collections); ?>
                </p>
            </div>
        <?php } ?>
    </div>
</div>