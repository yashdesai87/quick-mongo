<div class="card">
    <div class="card-header">
        <h2 class="card-title">Available Databases</h2>
        <p class="card-subtitle">Select a database to view its collections</p>
    </div>

    <div class="card-body">
        <?php if (empty($databases)) { ?>
            <div class="alert alert-info">
                No databases to show. The server is reachable, so either it holds
                nothing outside the system databases, which are not listed, or
                this MongoDB user is not allowed to list them. A user without
                that privilege can still open one directly by adding
                <code>?action=collections&amp;db=NAME</code> to the URL.
            </div>
        <?php } else { ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Database Name</th>
                        <th>Size on Disk</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($databases as $db) { ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?action=collections&db=<?php echo urlencode($db['name']); ?>"
                                       class="db-link">
                                        <?php echo Security::escape($db['name']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php echo View::formatBytes($db['sizeOnDisk']); ?>
                            </td>
                            <td>
                                <?php if ($db['empty']) { ?>
                                    <span class="badge badge-secondary">Empty</span>
                                <?php } else { ?>
                                    <span class="badge badge-success">Active</span>
                                <?php } ?>
                            </td>
                            <td>
                                <a href="?action=collections&db=<?php echo urlencode($db['name']); ?>"
                                   class="btn btn-sm btn-primary">
                                    View Collections
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="table-footer">
                <p class="text-muted">
                    Total databases: <?php echo count($databases); ?>
                </p>
            </div>
        <?php } ?>
    </div>
</div>