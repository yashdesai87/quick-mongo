/**
 * Quick Mongo - JavaScript interactions
 */

$(document).ready(function() {

    // Database selector change handler
    $('#database-selector').on('change', function() {
        const selectedDb = $(this).val();

        if (selectedDb) {
            // Navigate to collections view for selected database
            window.location.href = '?action=collections&db=' + encodeURIComponent(selectedDb);
        } else {
            // Go back to database list
            window.location.href = '?';
        }
    });

    // JSON tree toggle functionality
    $('.json-toggle').on('click', function(e) {
        e.stopPropagation();

        $(this).toggleClass('collapsed expanded');

        // Toggle the nested content
        const nested = $(this).next('.json-nested');
        if (nested.length) {
            nested.slideToggle(200);
        }
    });

    // Table row hover effect
    $('.table tbody tr').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );

    // Add loading indicator for navigation links
    $('a.db-link, a.collection-link, a.btn').on('click', function(e) {
        // Don't add loading for external links or anchors
        const href = $(this).attr('href');
        if (!href || href.startsWith('#') || href.startsWith('http')) {
            return;
        }

        // Add loading class to button
        if ($(this).hasClass('btn')) {
            $(this).addClass('loading-btn');
            $(this).prepend('<span class="loading-spinner"></span> ');
        }
    });

    // Smooth scroll for anchors
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 20
            }, 300);
        }
    });

    // Initialize Prism for syntax highlighting
    if (typeof Prism !== 'undefined') {
        Prism.highlightAll();
    }

    // Auto-collapse large JSON trees
    $('.json-tree.root > li > .json-toggle').each(function() {
        const nested = $(this).next('.json-nested');
        const items = nested.find('> ul > li').length;

        // Auto-collapse if more than 10 items
        if (items > 10) {
            $(this).addClass('collapsed').removeClass('expanded');
            nested.hide();
        }
    });

    // Add view toggle for JSON viewer
    if ($('.json-viewer').length && $('.tree-view').length) {
        // Add toggle buttons
        const toggleHtml = `
            <div class="view-toggle" style="margin-bottom: 15px;">
                <button class="btn btn-sm btn-primary" id="view-json">JSON View</button>
                <button class="btn btn-sm btn-secondary" id="view-tree">Tree View</button>
            </div>
        `;

        $('.document-content .section-title').after(toggleHtml);

        // Toggle between views
        $('#view-json').on('click', function() {
            $('.json-viewer').show();
            $('.tree-view').hide();
            $('#view-json').removeClass('btn-secondary').addClass('btn-primary');
            $('#view-tree').removeClass('btn-primary').addClass('btn-secondary');
        });

        $('#view-tree').on('click', function() {
            $('.json-viewer').hide();
            $('.tree-view').show();
            $('#view-tree').removeClass('btn-secondary').addClass('btn-primary');
            $('#view-json').removeClass('btn-primary').addClass('btn-secondary');
        });
    }

    // Pagination keyboard navigation
    $(document).on('keydown', function(e) {
        // Only on document list pages
        if (!$('.pagination').length) return;

        // Don't trigger if typing in input
        if ($(e.target).is('input, textarea, select')) return;

        // Left arrow - previous page
        if (e.keyCode === 37) {
            const prevLink = $('.pagination .page-item:first-child a.page-link');
            if (prevLink.length && !prevLink.parent().hasClass('disabled')) {
                window.location.href = prevLink.attr('href');
            }
        }

        // Right arrow - next page
        if (e.keyCode === 39) {
            const nextLink = $('.pagination .page-item:last-child a.page-link');
            if (nextLink.length && !nextLink.parent().hasClass('disabled')) {
                window.location.href = nextLink.attr('href');
            }
        }
    });

    // Highlight current database in sidebar
    const currentDb = $('#database-selector').val();
    if (currentDb) {
        $('.current-db-info strong').css('color', '#0066cc');
    }

    // Add tooltips for truncated text
    $('code').each(function() {
        const $this = $(this);
        if (this.scrollWidth > this.clientWidth) {
            $this.attr('title', $this.text());
        }
    });

    // Add active state to current page in sidebar
    const currentAction = new URLSearchParams(window.location.search).get('action');
    if (currentAction) {
        $(`.sidebar-link[href*="action=${currentAction}"]`).addClass('active');
    }

    // Expand/Collapse all for tree view
    if ($('.tree-view').length) {
        const expandAllBtn = $('<button class="btn btn-sm" style="margin-right: 10px;">Expand All</button>');
        const collapseAllBtn = $('<button class="btn btn-sm">Collapse All</button>');

        $('.view-toggle').append(expandAllBtn).append(collapseAllBtn);

        expandAllBtn.on('click', function() {
            $('.json-toggle').removeClass('collapsed').addClass('expanded');
            $('.json-nested').show();
        });

        collapseAllBtn.on('click', function() {
            $('.json-toggle').removeClass('expanded').addClass('collapsed');
            $('.json-nested').hide();
        });
    }

    // Timezone-aware timestamps
    initTimezone();

});

// --- Timezone handling -------------------------------------------------

function getSelectedTimezone() {
    return localStorage.getItem('qmTz') || Intl.DateTimeFormat().resolvedOptions().timeZone;
}

function formatRelative(date) {
    const diffMins = Math.round((Date.now() - date.getTime()) / 60000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return diffMins + (diffMins === 1 ? ' min ago' : ' mins ago');
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return diffHours + (diffHours === 1 ? ' hour ago' : ' hours ago');
    const diffDays = Math.floor(diffHours / 24);
    return diffDays + (diffDays === 1 ? ' day ago' : ' days ago');
}

function renderTimes() {
    const tz = getSelectedTimezone();
    let formatter;
    try {
        // Explicit parts only: dateStyle/timeStyle cannot combine with timeZoneName
        formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: tz,
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false,
            timeZoneName: 'short'
        });
    } catch (e) {
        return; // invalid timezone; keep server-rendered UTC fallback
    }

    document.querySelectorAll('.js-time').forEach(function(el) {
        const date = new Date(el.getAttribute('datetime'));
        if (isNaN(date)) return;

        const parts = {};
        formatter.formatToParts(date).forEach(function(p) { parts[p.type] = p.value; });
        const text = parts.year + '-' + parts.month + '-' + parts.day + ' '
            + parts.hour + ':' + parts.minute + ':' + parts.second
            + (parts.timeZoneName ? ' ' + parts.timeZoneName : '');

        // Replace contents each pass so re-renders stay idempotent
        el.replaceChildren(document.createTextNode(text));
        if (el.hasAttribute('data-relative')) {
            const rel = document.createElement('div');
            rel.className = 'time-relative';
            rel.textContent = formatRelative(date);
            el.appendChild(rel);
        }
    });
}

function initTimezone() {
    const select = document.getElementById('tz-selector');
    if (!select) {
        renderTimes();
        return;
    }

    const current = getSelectedTimezone();
    let zones = [];
    if (typeof Intl.supportedValuesOf === 'function') {
        zones = Intl.supportedValuesOf('timeZone');
    }
    // supportedValuesOf omits UTC and may omit the active zone
    if (!zones.includes('UTC')) zones.unshift('UTC');
    if (current && !zones.includes(current)) zones.unshift(current);

    zones.forEach(function(tz) {
        const opt = document.createElement('option');
        opt.value = tz;
        opt.textContent = tz;
        if (tz === current) opt.selected = true;
        select.appendChild(opt);
    });

    select.addEventListener('change', function() {
        localStorage.setItem('qmTz', select.value);
        renderTimes();
    });

    renderTimes();
}
