<?php
/**
 * HydroLogic OS – Global Helper Functions
 * ========================================
 * Provides: authentication guards, CSRF, flash messages,
 * XSS sanitization, and pagination.
 *
 * Include AFTER db_connect.php (session is already started there).
 */

// ═══════════════════════════════════════════════════════════
// AUTHENTICATION & RBAC
// ═══════════════════════════════════════════════════════════

/**
 * Require the user to be logged in.
 * Redirects to login.php if no valid session exists.
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Require the user to have a specific role.
 * Call AFTER require_login().
 *
 * @param string $role  'Admin' or 'Operator'
 */
function require_role(string $role): void {
    if (($_SESSION['role'] ?? '') !== $role) {
        // Redirect operator to their dashboard instead of 403
        if ($_SESSION['role'] === 'Operator') {
            header('Location: ' . base_url('operator/dashboard.php'));
        } else {
            header('Location: ' . base_url('login.php'));
        }
        exit;
    }
}

/**
 * Check if the currently logged-in user has a given role.
 *
 * @param  string $role
 * @return bool
 */
function has_role(string $role): bool {
    return ($_SESSION['role'] ?? '') === $role;
}

/**
 * Return the currently logged-in user's display name.
 */
function current_user_name(): string {
    return htmlspecialchars($_SESSION['user_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
}

/**
 * Return the currently logged-in user's role.
 */
function current_role(): string {
    return $_SESSION['role'] ?? '';
}

// ═══════════════════════════════════════════════════════════
// URL HELPERS
// ═══════════════════════════════════════════════════════════

/**
 * Build an absolute URL relative to the project root.
 * Detects the sub-folder name automatically from $_SERVER.
 *
 * @param  string $path  e.g. 'admin/dashboard.php'
 * @return string
 */
function base_url(string $path = ''): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up until we find the project root segment
    $parts  = explode('/', trim($script, '/'));
    // The project folder is the first segment after the host
    $base   = '/' . ($parts[0] ?? '');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// ═══════════════════════════════════════════════════════════
// XSS SANITIZATION
// ═══════════════════════════════════════════════════════════

/**
 * Sanitize a value for safe HTML output.
 *
 * @param  mixed $value
 * @return string
 */
function sanitize($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize and trim a POST/GET input.
 *
 * @param  string $key      The input key
 * @param  string $method   'POST' or 'GET'
 * @param  mixed  $default  Default value if key missing
 * @return string
 */
function input(string $key, string $method = 'POST', $default = ''): string {
    $source = strtoupper($method) === 'GET' ? $_GET : $_POST;
    return sanitize(trim($source[$key] ?? $default));
}

// ═══════════════════════════════════════════════════════════
// CSRF PROTECTION
// ═══════════════════════════════════════════════════════════

/**
 * Generate (or retrieve) a CSRF token for the current session.
 *
 * @return string  The token value
 */
function generate_csrf(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Render a hidden CSRF token field for use inside <form> tags.
 *
 * @return string  HTML hidden input element
 */
function csrf_field(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generate_csrf() . '">';
}

/**
 * Validate the CSRF token from a POST request.
 * Terminates with a 403 if invalid.
 */
function validate_csrf(): void {
    $token         = $_POST[CSRF_TOKEN_NAME] ?? '';
    $session_token = $_SESSION[CSRF_TOKEN_NAME] ?? '';

    if (empty($token) || !hash_equals($session_token, $token)) {
        http_response_code(403);
        die('<div style="font-family:monospace;padding:2rem;color:#ba1a1a;">
                <strong>403 Forbidden:</strong> CSRF token mismatch. 
                This request has been blocked for security reasons.
             </div>');
    }

    // Regenerate token after successful validation (one-time use)
    unset($_SESSION[CSRF_TOKEN_NAME]);
}

// ═══════════════════════════════════════════════════════════
// FLASH MESSAGES
// ═══════════════════════════════════════════════════════════

/**
 * Store a flash message in the session.
 *
 * @param string $type     'success' | 'error' | 'warning' | 'info'
 * @param string $message  The message text
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the stored flash message.
 * Returns null if no flash message is set.
 *
 * @return array|null  ['type' => string, 'message' => string]
 */
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render the flash message as an HTML alert banner.
 * Uses the Tailwind color tokens from the design system.
 *
 * @return string  HTML markup or empty string
 */
function render_flash(): string {
    $flash = get_flash();
    if (!$flash) return '';

    $colors = [
        'success' => 'bg-secondary/10 border-secondary text-secondary',
        'error'   => 'bg-error-container border-error text-on-error-container',
        'warning' => 'bg-tertiary-fixed/30 border-tertiary-fixed-dim text-on-tertiary-fixed-variant',
        'info'    => 'bg-primary/5 border-primary text-primary',
    ];
    $icons  = [
        'success' => 'check_circle',
        'error'   => 'error',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    $cls  = $colors[$flash['type']] ?? $colors['info'];
    $icon = $icons[$flash['type']]  ?? 'info';
    $msg  = sanitize($flash['message']);

    return <<<HTML
    <div class="flex items-start gap-md p-md mb-lg border-l-4 rounded-lg {$cls}" role="alert" id="flash-msg">
        <span class="material-symbols-outlined mt-xs" style="font-variation-settings:'FILL' 1;">{$icon}</span>
        <div class="flex-1">
            <p class="font-body-md">{$msg}</p>
        </div>
        <button onclick="document.getElementById('flash-msg').remove()" class="opacity-60 hover:opacity-100">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>
    HTML;
}

// ═══════════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════════

/**
 * Calculate pagination metadata.
 *
 * @param  int $total_records  Total number of records
 * @param  int $per_page       Records per page
 * @param  int $current_page   Current page number (1-indexed)
 * @return array               ['total_pages','offset','current_page','per_page']
 */
function paginate(int $total_records, int $per_page, int $current_page): array {
    $current_page = max(1, $current_page);
    $total_pages  = max(1, (int) ceil($total_records / $per_page));
    $current_page = min($current_page, $total_pages);
    $offset       = ($current_page - 1) * $per_page;

    return [
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'per_page'     => $per_page,
        'offset'       => $offset,
        'total_records'=> $total_records,
    ];
}

/**
 * Render a Tailwind-styled pagination control.
 *
 * @param  array  $pager     Result from paginate()
 * @param  string $base_url  URL prefix (e.g. '?page=')
 * @return string
 */
function render_pagination(array $pager, string $base_url): string {
    if ($pager['total_pages'] <= 1) return '';

    $html   = '<div class="flex items-center gap-xs">';
    $cur    = $pager['current_page'];
    $total  = $pager['total_pages'];
    $from   = max(1, $cur - 2);
    $to     = min($total, $cur + 2);

    for ($i = $from; $i <= $to; $i++) {
        $active = ($i === $cur)
            ? 'bg-primary text-on-primary font-bold'
            : 'hover:bg-surface-container-highest text-on-surface-variant';
        $html .= "<a href=\"{$base_url}{$i}\" "
               . "class=\"w-10 h-10 flex items-center justify-center rounded-full font-body-md transition-colors {$active}\">"
               . "{$i}</a>";
    }

    $html .= '</div>';
    return $html;
}

// ═══════════════════════════════════════════════════════════
// STATUS BADGE HELPERS
// ═══════════════════════════════════════════════════════════

/**
 * Return Tailwind classes + icon for a tube well status badge.
 */
function well_status_badge(string $status): string {
    return match($status) {
        'Active'           => '<span class="inline-flex items-center px-sm py-xs rounded-full bg-secondary/10 text-secondary text-[10px] font-bold uppercase tracking-wider border border-secondary/20"><span class="w-1.5 h-1.5 rounded-full bg-secondary mr-1.5"></span>Active</span>',
        'Under Maintenance'=> '<span class="inline-flex items-center px-sm py-xs rounded-full bg-tertiary-fixed-dim/20 text-on-tertiary-container text-[10px] font-bold uppercase tracking-wider border border-tertiary-fixed-dim/40"><span class="w-1.5 h-1.5 rounded-full bg-tertiary-fixed-dim mr-1.5"></span>Maintenance</span>',
        'Inactive'         => '<span class="inline-flex items-center px-sm py-xs rounded-full bg-error/10 text-error text-[10px] font-bold uppercase tracking-wider border border-error/20"><span class="w-1.5 h-1.5 rounded-full bg-error mr-1.5"></span>Inactive</span>',
        default            => '<span class="inline-flex items-center px-sm py-xs rounded-full bg-surface-container-highest text-on-surface-variant text-[10px] font-bold uppercase tracking-wider">' . sanitize($status) . '</span>',
    };
}

/**
 * Return Tailwind badge for maintenance ticket status.
 */
function ticket_status_badge(string $status): string {
    return match($status) {
        'Pending'     => '<span class="px-2 py-1 bg-surface-container-highest text-on-surface-variant text-[10px] font-black uppercase rounded">Pending</span>',
        'In Progress' => '<span class="px-2 py-1 bg-tertiary-fixed/30 text-on-tertiary-fixed-variant text-[10px] font-black uppercase rounded">In Progress</span>',
        'Completed'   => '<span class="px-2 py-1 bg-secondary/10 text-secondary text-[10px] font-black uppercase rounded">Completed</span>',
        default       => '<span class="px-2 py-1 bg-surface-container-highest text-on-surface-variant text-[10px] font-black uppercase rounded">' . sanitize($status) . '</span>',
    };
}

/**
 * Return Tailwind badge for maintenance severity.
 */
function severity_badge(string $severity): string {
    return match($severity) {
        'Critical' => '<span class="px-2 py-1 bg-error-container text-on-error-container text-[10px] font-black uppercase rounded">Critical</span>',
        'Warning'  => '<span class="px-2 py-1 bg-tertiary-fixed/30 text-on-tertiary-fixed-variant text-[10px] font-black uppercase rounded">Warning</span>',
        'Routine'  => '<span class="px-2 py-1 bg-surface-container-highest text-on-surface-variant text-[10px] font-black uppercase rounded">Routine</span>',
        default    => '<span class="px-2 py-1 bg-surface-container-highest text-on-surface-variant text-[10px] font-black uppercase rounded">' . sanitize($severity) . '</span>',
    };
}
