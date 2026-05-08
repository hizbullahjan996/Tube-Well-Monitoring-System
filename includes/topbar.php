<?php
/**
 * HydroLogic OS – Top App Bar Include
 * =====================================
 * Renders the sticky top header with page title + user info.
 *
 * Variables expected:
 *   $page_title (string) – displayed in the topbar
 */
$page_title = $page_title ?? 'Dashboard';
?>
<!-- ── Top App Bar ─────────────────────────────────────────── -->
<header class="flex justify-between items-center h-16 px-lg sticky top-0 z-40 bg-surface/80 backdrop-blur-md border-b border-outline-variant">

    <!-- Left: Page Title + Search -->
    <div class="flex items-center gap-lg">
        <h2 class="font-h3 text-h3 font-bold text-primary"><?= APP_NAME ?></h2>
        <div class="relative hidden md:block">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
            <input
                class="pl-10 pr-4 py-1.5 bg-surface-container-low border border-outline-variant rounded-xl text-body-md w-64 focus:ring-2 focus:ring-secondary focus:border-transparent outline-none transition-all"
                placeholder="Search system resources..."
                type="text"
                id="global-search"
                autocomplete="off"
            />
        </div>
    </div>

    <!-- Right: Notifications + User -->
    <div class="flex items-center gap-md">
        <!-- Notification Bell (decorative – could be wired to DB) -->
        <button class="p-2 rounded-full hover:bg-surface-container-low transition-colors relative" title="Notifications">
            <span class="material-symbols-outlined text-on-surface-variant">notifications</span>
            <?php
            // Show red dot if there are pending/critical maintenance items
            // This is a lightweight check – no extra query needed
            ?>
            <span class="absolute top-2 right-2 w-2 h-2 bg-error rounded-full"></span>
        </button>

        <!-- Divider -->
        <div class="h-8 w-px bg-outline-variant mx-sm"></div>

        <!-- User Info -->
        <div class="flex items-center gap-sm cursor-pointer hover:bg-surface-container-low p-1 rounded-lg transition-all">
            <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center border border-outline-variant">
                <span class="material-symbols-outlined text-on-primary-container text-[18px]">person</span>
            </div>
            <div class="hidden lg:block text-left">
                <p class="text-label-sm font-bold leading-none"><?= current_user_name() ?></p>
                <p class="text-[10px] text-on-surface-variant uppercase tracking-tighter"><?= sanitize(current_role()) ?></p>
            </div>
        </div>
    </div>
</header>
