<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Security::escape($title ?? 'MongoDB Browser'); ?> - Quick Mongo</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(ASSETS_PATH.'/style.css'); ?>">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
            integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs"
            crossorigin="anonymous"></script>

    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet"
          integrity="sha384-rCCjoCPCsizaAAYVoz1Q0CmCTvnctK0JkfCSjx7IIxexTBg+uCKtFYycedUjMyA2"
          crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"
            integrity="sha384-06z5D//U/xpvxZHuUz92xBvq3DqBBFi7Up53HRrbV7Jlv7Yvh/MZ7oenfUe9iCEt"
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"
            integrity="sha384-RhrmFFMb0ZCHImjFMpR/UE3VEtIVTCtNrtKQqXCzqXZNJala02N3UbVhi+qzw3CY"
            crossorigin="anonymous"></script>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-content">
                <h1 class="app-title">
                    <span class="logo-icon">🗄️</span>
                    Quick Mongo
                </h1>
                <div class="header-info">
                    <label for="tz-selector">Timezone</label>
                    <select id="tz-selector" title="Display timezone for timestamps"></select>
                </div>
            </div>
        </header>

        <!-- Main Container -->
        <div class="main-container">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Databases</h3>
                    <div class="database-selector">
                        <select id="database-selector" class="form-select">
                            <option value="">Select Database...</option>
                            <?php if (! empty($databases)) { ?>
                                <?php foreach ($databases as $db) { ?>
                                    <option value="<?php echo Security::escape($db['name']); ?>"
                                            <?php echo ($currentDatabase === $db['name']) ? 'selected' : ''; ?>>
                                        <?php echo Security::escape($db['name']); ?>
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if (($currentDatabase ?? '') !== '') { ?>
                <div class="sidebar-section">
                    <h4 class="sidebar-subtitle">Current Database</h4>
                    <div class="current-db-info">
                        <strong><?php echo Security::escape($currentDatabase); ?></strong>
                    </div>

                    <div class="sidebar-nav">
                        <a href="?action=collections&db=<?php echo urlencode($currentDatabase); ?>"
                           class="sidebar-link <?php echo ($currentAction === 'collections') ? 'active' : ''; ?>">
                            📁 Collections
                        </a>
                    </div>
                </div>
                <?php } ?>

                <div class="sidebar-section sidebar-footer">
                    <a href="?" class="sidebar-link">
                        🏠 All Databases
                    </a>
                </div>
            </aside>

            <!-- Content Area -->
            <main class="content">
                <!-- Breadcrumb -->
                <?php if (! empty($breadcrumbs)) { ?>
                <nav class="breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb) { ?>
                        <?php if ($crumb['url']) { ?>
                            <a href="<?php echo Security::escape($crumb['url']); ?>" class="breadcrumb-item">
                                <?php echo Security::escape($crumb['label']); ?>
                            </a>
                        <?php } else { ?>
                            <span class="breadcrumb-item active">
                                <?php echo Security::escape($crumb['label']); ?>
                            </span>
                        <?php } ?>

                        <?php if ($index < count($breadcrumbs) - 1) { ?>
                            <span class="breadcrumb-separator">/</span>
                        <?php } ?>
                    <?php } ?>
                </nav>
                <?php } ?>

                <!-- Page Content -->
                <div class="page-content">
                    <?php echo $content; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/script.js?v=<?php echo filemtime(ASSETS_PATH.'/script.js'); ?>"></script>
</body>
</html>