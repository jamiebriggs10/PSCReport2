<?php
require_once 'includes/auth.php';
require_once 'includes/utils.php';
requireAuth(); // Only require login, not admin

$pdo = getDbConnection();
$errors = [];
$messages = [];
$isAdmin = isAdmin();

// Get users for assignment dropdown (show all users)
try {
    $stmt = $pdo->query("
        SELECT id, full_name, username, role
        FROM users 
        WHERE is_active = 1 
        ORDER BY role DESC, full_name ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
    $users = [];
}

$pageTitle = 'Maintenance Calendar';
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* =====================================================================
   Maintenance Calendar — Mobile-first design system
   Aligned to global tokens (assets/css/style.css). Class names preserved.
   ===================================================================== */

/* Sticky page header for mobile context */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    margin-bottom: 1rem;
}
.page-header h1 { font-size: 1.4rem; }
.page-header > div:first-child { min-width: 0; }
.header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

/* View toggle (Month / Week / Day) — segmented control */
.view-controls {
    display: inline-flex;
    background: var(--light-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 3px;
    gap: 3px;
    flex: 1;
    min-width: 220px;
    max-width: 360px;
}
.view-btn {
    flex: 1;
    text-align: center;
    padding: 8px 10px;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 7px;
    transition: background 160ms ease, color 160ms ease, box-shadow 160ms ease;
    cursor: pointer;
    user-select: none;
}
.view-btn:hover { color: var(--text-color); background: var(--surface-color); }
.view-btn.active {
    background: var(--surface-color);
    color: var(--primary-color);
    box-shadow: var(--shadow-xs);
    font-weight: 600;
}

/* ====== Calendar shell ====== */
.calendar-container {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xs);
    overflow: hidden;
    margin-bottom: 6rem; /* space for FAB */
}

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--surface-alt);
    position: sticky;
    top: 64px;
    z-index: 50;
}

.calendar-nav {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.calendar-nav button {
    background: var(--surface-color);
    color: var(--text-color);
    border: 1px solid var(--border-strong);
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 9px;
    cursor: pointer;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 160ms ease, color 160ms ease, border-color 160ms ease, transform 80ms ease;
    font-family: inherit;
}
.calendar-nav button:hover { background: var(--light-color); border-color: var(--primary-color); color: var(--primary-color); }
.calendar-nav button:active { transform: translateY(1px); }
.calendar-nav button[onclick*="today"] {
    width: auto;
    padding: 0 0.85rem;
    font-weight: 600;
    font-size: 0.8rem;
}

.calendar-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--text-color);
    margin: 0;
    letter-spacing: -0.01em;
    text-align: right;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ====== Month grid ====== */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border-color);
}
.calendar-day-header {
    background: var(--surface-alt);
    padding: 0.6rem 0.4rem;
    text-align: center;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.calendar-day {
    background: var(--surface-color);
    min-height: 110px;
    padding: 0.4rem;
    position: relative;
    cursor: pointer;
    transition: background 140ms ease;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.calendar-day:hover { background: var(--primary-50); }
.calendar-day.other-month { background: #fafbfc; }
.calendar-day.other-month .day-number { color: var(--text-subtle); }
.calendar-day.selected {
    background: var(--primary-50);
    box-shadow: inset 0 0 0 2px var(--accent);
}

.day-number {
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--text-color);
    line-height: 1;
    padding: 4px 2px 2px;
    font-variant-numeric: tabular-nums;
}
.day-number.today {
    background: var(--primary-color);
    color: #fff;
    border-radius: 999px;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    box-shadow: 0 2px 6px -2px rgba(15, 37, 64, 0.35);
}

/* Calendar event chips */
.calendar-event {
    background: var(--primary-color);
    color: #fff;
    padding: 2px 6px;
    margin: 0;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: filter 140ms ease, transform 140ms ease;
    border-left: 3px solid rgba(0,0,0,0.25);
    line-height: 1.5;
}
.calendar-event:hover { filter: brightness(1.08); transform: translateX(1px); }
.calendar-event.priority-low      { background: #059669; border-left-color: #047857; }
.calendar-event.priority-medium   { background: #d97706; border-left-color: #b45309; color: #fff; }
.calendar-event.priority-high     { background: #ea580c; border-left-color: #c2410c; }
.calendar-event.priority-urgent   { background: var(--danger-color); border-left-color: #991b1b; }
.calendar-event.status-completed,
.calendar-event.status-resolved {
    background: var(--light-color) !important;
    color: var(--text-muted) !important;
    text-decoration: line-through;
    border-left-color: var(--border-strong);
}

/* ====== Floating add button ====== */
.add-event-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    background: var(--primary-color);
    background-image: linear-gradient(140deg, var(--primary-soft) 0%, var(--primary-color) 100%);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 50%;
    font-size: 1.3rem;
    cursor: pointer;
    box-shadow: 0 10px 24px -6px rgba(15, 37, 64, 0.45), 0 4px 8px rgba(15, 37, 64, 0.18);
    transition: transform 200ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 200ms ease;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent;
}
.add-event-btn:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 16px 32px -8px rgba(15,37,64,0.55); }
.add-event-btn:active { transform: scale(0.96); }

/* ====== Modals (override globals only where needed) ====== */
.modal { display: none; }
.modal.show { display: flex; }
.modal-content { width: 100%; max-width: 540px; }
.event-details-enhanced, .subscription-modal { max-width: 600px; }

.modal-header h3 { font-size: 1.05rem; margin: 0; color: var(--text-color); }
.modal-close {
    background: var(--light-color);
    border: 1px solid var(--border-color);
    width: 32px; height: 32px;
    border-radius: 9px;
    cursor: pointer;
    color: var(--text-muted);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    transition: background 140ms ease, color 140ms ease;
    padding: 0;
    line-height: 1;
}
.modal-close:hover { background: var(--danger-soft); color: var(--danger-color); }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.checkbox-group {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--surface-color);
    cursor: pointer;
}
.checkbox-group input[type=checkbox] { accent-color: var(--primary-color); }

.upload-content { padding: 0.5rem; }
.upload-content p { margin: 0.25rem 0; color: var(--text-muted); font-size: 0.85rem; }
.upload-content p:first-of-type { color: var(--text-color); font-weight: 500; font-size: 0.9rem; }
.upload-content i { color: var(--text-muted) !important; }

/* ====== Event details modal — info card ====== */
.event-info-card {
    background: var(--surface-alt);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
}
.event-info-card .event-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 0.5rem;
    letter-spacing: -0.01em;
}
.event-info-card .event-time {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-variant-numeric: tabular-nums;
}
.event-info-card .event-priority { display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.5rem; }

.event-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 1rem;
    margin: 1rem 0;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    background: var(--surface-color);
}
.meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.meta-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-subtle);
    font-weight: 600;
}
.meta-value {
    font-size: 0.875rem;
    color: var(--text-color);
    font-weight: 500;
    word-break: break-word;
}
.meta-icon {
    display: inline-flex;
    width: 14px;
    justify-content: center;
    color: var(--text-muted);
    margin-right: 4px;
}

.event-description {
    margin: 1rem 0;
    padding: 1rem;
    background: var(--surface-alt);
    border-left: 3px solid var(--accent);
    border-radius: 0 10px 10px 0;
}
.event-description .description-text {
    color: var(--text-color);
    font-size: 0.9rem;
    line-height: 1.55;
    white-space: pre-wrap;
}

/* Priority + status badges (details modal) */
.priority-badge, .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: #fff;
    border-radius: 999px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    line-height: 1.4;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
}
.priority-badge.priority-low      { background: #059669; }
.priority-badge.priority-medium   { background: #d97706; }
.priority-badge.priority-high     { background: #ea580c; }
.priority-badge.priority-urgent   { background: var(--danger-color); }

.status-badge.status-scheduled    { background: var(--info-color); }
.status-badge.status-in_progress,
.status-badge.status-in-progress  { background: var(--warning-color); }
.status-badge.status-completed,
.status-badge.status-resolved     { background: var(--success-color); }
.status-badge.status-cancelled    { background: var(--text-muted); }

/* Attachments grid in event details */
.attachments-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-color);
    margin: 1rem 0 0.5rem;
    font-size: 0.875rem;
}
.attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.6rem;
}
.attachment-item {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: hidden;
    background: var(--surface-color);
    text-decoration: none;
    color: inherit;
    transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
    display: flex;
    flex-direction: column;
}
.attachment-item:hover { transform: translateY(-1px); box-shadow: var(--shadow); border-color: var(--border-strong); }
.attachment-preview {
    aspect-ratio: 1 / 1;
    width: 100%;
    object-fit: cover;
    background: var(--light-color);
    display: block;
}
.attachment-icon {
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--light-color);
    color: var(--text-muted);
    font-size: 1.75rem;
}
.attachment-name {
    padding: 0.5rem;
    font-size: 0.72rem;
    color: var(--text-color);
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-top: 1px solid var(--border-color);
    background: var(--surface-alt);
}

/* ====== Resolution modal ====== */
.completed-event-summary {
    background: var(--primary-50);
    border: 1px solid var(--primary-100);
    color: var(--primary-color);
    font-weight: 600;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin: 0.5rem 0 1rem;
}
.resolution-section, .resolution-content { margin-top: 1rem; }
.resolution-header {
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.resolution-notes {
    background: var(--surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    font-size: 0.875rem;
    line-height: 1.5;
    white-space: pre-wrap;
}
.resolution-metadata { margin-top: 1rem; }
.resolution-meta { font-size: 0.78rem; color: var(--text-muted); }

/* ====== Subscription modal ====== */
.subscription-intro p {
    color: var(--text-muted);
    font-size: 0.875rem;
    line-height: 1.55;
    margin-bottom: 1rem;
}
.subscription-options { display: flex; flex-direction: column; gap: 0.9rem; }
.subscription-option {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1rem;
    background: var(--surface-color);
}
.subscription-option.quick-setup {
    background: linear-gradient(180deg, var(--primary-50), var(--surface-color));
    border-color: var(--primary-100);
}
.option-header h4 {
    font-size: 0.9rem;
    margin: 0 0 0.5rem;
    color: var(--text-color);
    font-weight: 600;
}
.subscription-option p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}
.btn-large {
    padding: 0.75rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 600;
    width: 100%;
    justify-content: center;
}

.calendar-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 4px;
    background: var(--light-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 0.85rem;
}
.tab-btn {
    flex: 1;
    min-width: 0;
    padding: 7px 10px;
    background: transparent;
    border: 0;
    border-radius: 7px;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
    font-family: inherit;
    white-space: nowrap;
}
.tab-btn:hover { color: var(--text-color); }
.tab-btn.active {
    background: var(--surface-color);
    color: var(--primary-color);
    box-shadow: var(--shadow-xs);
    font-weight: 600;
}

.calendar-instructions { margin-top: 0.5rem; }
.instruction-content { display: none; }
.instruction-content.active { display: block; }
.instruction-content h5 {
    font-size: 0.85rem;
    color: var(--text-color);
    margin-bottom: 0.5rem;
}
.instruction-steps {
    padding-left: 1.2rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1.7;
}
.instruction-steps strong { color: var(--text-color); }

.url-section {
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-color);
}
.url-section label {
    display: block;
    margin-bottom: 0.4rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 500;
}
.url-input-group { display: flex; gap: 0.4rem; align-items: stretch; }
.url-input-group .form-control {
    font-family: var(--font-mono);
    font-size: 0.78rem;
    padding: 0.5rem 0.6rem;
    background: var(--light-color);
    color: var(--text-color);
}
.url-input-group .btn { white-space: nowrap; padding: 0.5rem 0.85rem; font-size: 0.8rem; }

.subscription-notes {
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    background: var(--warning-soft);
    border: 1px solid #fde68a;
    border-radius: 10px;
    color: #78350f;
}
.subscription-notes h5 {
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
    color: #78350f;
}
.subscription-notes ul {
    margin: 0;
    padding-left: 1.1rem;
    font-size: 0.8rem;
    line-height: 1.65;
}

/* ====== WEEK VIEW ====== */
.calendar-grid.week-view { display: block; background: transparent; gap: 0; }
.week-container {
    display: grid;
    grid-template-columns: 56px repeat(7, minmax(120px, 1fr));
    background: var(--border-color);
    gap: 1px;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}
.time-column { display: contents; }
.time-slot-header,
.day-column .day-header {
    background: var(--surface-alt);
    padding: 0.55rem 0.4rem;
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 5;
}
.day-column .day-header { background: var(--surface-color); border-bottom: 1px solid var(--border-color); }
.day-column .day-header .day-name { color: var(--text-muted); font-size: 0.65rem; }
.day-column .day-header .day-number { font-size: 1rem; color: var(--text-color); padding: 2px 0 0; display: inline-block; }
.day-column .day-header .day-number.today {
    background: var(--primary-color);
    color: #fff;
    width: 26px; height: 26px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 2px auto 0;
}

.time-slot, .time-slot-day {
    background: var(--surface-alt);
    padding: 4px 6px 0;
    font-size: 0.68rem;
    color: var(--text-subtle);
    text-align: right;
    font-variant-numeric: tabular-nums;
    border-top: 1px solid var(--border-color);
    min-height: 48px;
}
.day-column { display: contents; }
.hour-slot {
    background: var(--surface-color);
    min-height: 48px;
    border-top: 1px solid var(--border-color);
    padding: 2px 3px;
    position: relative;
    cursor: pointer;
    transition: background 140ms ease;
}
.hour-slot:hover { background: var(--primary-50); }

.week-event, .day-event {
    display: block;
    background: var(--primary-color);
    color: #fff;
    padding: 3px 6px;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    border-left: 3px solid rgba(0,0,0,0.25);
    transition: filter 140ms ease;
}
.week-event:hover, .day-event:hover { filter: brightness(1.08); }
.week-event.priority-low,    .day-event.priority-low    { background: #059669; border-left-color: #047857; }
.week-event.priority-medium, .day-event.priority-medium { background: #d97706; border-left-color: #b45309; }
.week-event.priority-high,   .day-event.priority-high   { background: #ea580c; border-left-color: #c2410c; }
.week-event.priority-urgent, .day-event.priority-urgent { background: var(--danger-color); border-left-color: #991b1b; }
.week-event.status-resolved, .day-event.status-resolved,
.week-event.status-completed, .day-event.status-completed {
    background: var(--light-color) !important;
    color: var(--text-muted) !important;
    text-decoration: line-through;
    border-left-color: var(--border-strong);
}

/* ====== DAY VIEW ====== */
.calendar-grid.day-view { display: block; background: transparent; }
.day-container {
    display: grid;
    grid-template-columns: 64px 1fr;
    background: var(--border-color);
    gap: 1px;
}
.day-header-single {
    grid-column: 1 / -1;
    background: var(--surface-color);
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.day-header-single .day-info { display: flex; align-items: center; gap: 0.65rem; }
.day-header-single .day-name { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.day-header-single .day-number { font-size: 1.5rem; font-weight: 700; color: var(--text-color); padding: 0; }
.day-header-single .day-number.today {
    background: var(--primary-color);
    color: #fff;
    width: 38px; height: 38px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* ====== Mobile-first refinements ====== */
@media (max-width: 768px) {
    .page-header h1 { font-size: 1.25rem; }
    .header-actions .btn-outline { padding: 0.45rem 0.7rem; font-size: 0.78rem; }
    .header-actions { gap: 0.4rem; }
    .header-actions > *:not(.view-controls) { flex: 1; }
    .view-controls { width: 100%; max-width: 100%; }

    .calendar-header {
        padding: 0.6rem 0.7rem;
        top: 60px;
    }
    .calendar-nav button { width: 34px; height: 34px; }
    .calendar-nav button[onclick*="today"] { padding: 0 0.7rem; }
    .calendar-title { font-size: 0.95rem; }

    .calendar-day-header {
        padding: 0.45rem 0.2rem;
        font-size: 0.6rem;
    }
    .calendar-day {
        min-height: 64px;
        padding: 0.25rem;
    }
    .day-number { font-size: 0.78rem; padding: 2px; }
    .day-number.today { width: 22px; height: 22px; font-size: 0.72rem; }

    /* Show count dots instead of full event labels on mobile month view */
    .calendar-event {
        font-size: 0;
        padding: 0;
        margin: 0;
        height: 6px;
        width: 6px;
        border-radius: 999px;
        border-left: 0;
        display: inline-block;
        margin-right: 2px;
    }
    .calendar-day { gap: 0; }
    .calendar-day .calendar-event:nth-of-type(n+5) { display: none; }
    .calendar-day::after {
        content: "";
        flex: 1;
    }

    /* Modals: bottom sheet on mobile */
    .modal { padding: 0; align-items: flex-end; }
    .modal-content {
        max-width: 100%;
        width: 100%;
        max-height: 92vh;
        border-radius: 18px 18px 0 0;
        margin: 0;
        animation: sheetUp 240ms cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes sheetUp {
        from { transform: translateY(100%); }
        to   { transform: translateY(0); }
    }
    .modal-header {
        position: sticky;
        top: 0;
        background: var(--surface-color);
        z-index: 2;
        padding: 0.85rem 1rem;
    }
    .modal-header::before {
        content: "";
        position: absolute;
        top: 6px;
        left: 50%;
        transform: translateX(-50%);
        width: 36px;
        height: 4px;
        border-radius: 999px;
        background: var(--border-strong);
    }
    .modal-header h3 { margin-top: 6px; }
    .modal-body { padding: 1rem; }
    .modal-footer {
        position: sticky;
        bottom: 0;
        background: var(--surface-color);
        padding: 0.75rem 1rem;
        padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));
        gap: 0.5rem;
    }
    .modal-footer .btn { flex: 1; }

    .form-row { grid-template-columns: 1fr; }
    .event-meta-grid { grid-template-columns: 1fr; gap: 0.65rem; padding: 0.85rem; }
    .url-input-group { flex-direction: column; }
    .url-input-group .btn { width: 100%; }

    .tab-btn { font-size: 0.72rem; padding: 6px 8px; }

    .add-event-btn {
        bottom: 18px;
        right: 18px;
        width: 54px;
        height: 54px;
    }

    /* Week view: shrink columns; allow horizontal scroll */
    .week-container {
        grid-template-columns: 44px repeat(7, minmax(78px, 1fr));
    }
    .week-event { font-size: 0.62rem; padding: 2px 4px; }
    .time-slot, .time-slot-day { font-size: 0.62rem; min-height: 44px; }
    .hour-slot { min-height: 44px; }

    /* Day view */
    .day-container { grid-template-columns: 48px 1fr; }
    .day-header-single { padding: 0.7rem 0.85rem; }
    .day-header-single .day-number { font-size: 1.25rem; }
}

@media (max-width: 480px) {
    .view-btn { padding: 7px 6px; font-size: 0.75rem; }
    .calendar-day { min-height: 56px; }
}

/* Desktop refinements */
@media (min-width: 769px) {
    .page-header {
        flex-direction: row;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    .header-actions { flex-wrap: nowrap; }
}
</style>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Maintenance Calendar</h1>
                <p class="page-description">Schedule and manage maintenance events and tasks</p>
            </div>
            <div class="header-actions">
                <div class="view-controls">
                    <a href="#" class="view-btn active" data-view="month">Month</a>
                    <a href="#" class="view-btn" data-view="week">Week</a>
                    <a href="#" class="view-btn" data-view="day">Day</a>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="showSubscriptionModal()">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                    Subscribe to Calendar
                </button>
                <a href="index.php" class="btn btn-outline btn-sm">
                    ← Dashboard
                </a>
            </div>
        </div>

        <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><?= h($m) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= h($e) ?></div>
        <?php endforeach; ?>

        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button type="button" onclick="navigateCalendar('prev')" title="Previous Month">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" onclick="navigateCalendar('today')" title="Today">
                        Today
                    </button>
                    <button type="button" onclick="navigateCalendar('next')" title="Next Month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <h2 class="calendar-title" id="calendarTitle">
                    <!-- Will be populated by JavaScript -->
                </h2>
            </div>
            
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar will be populated by JavaScript -->
            </div>
        </div>

        <!-- Floating Add Button -->
        <button type="button" class="add-event-btn" onclick="showAddEventModal()" title="Add Maintenance Event">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</main>

<!-- Add/Edit Event Modal -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Maintenance Event</h3>
            <button type="button" class="modal-close" onclick="closeEventModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="eventForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" id="eventId" name="event_id" value="">
                <input type="hidden" id="editMode" name="edit_mode" value="0">

                <div class="form-group">
                    <label for="eventTitle" class="form-label">Event Title *</label>
                    <input 
                        type="text" 
                        id="eventTitle" 
                        name="title" 
                        class="form-control" 
                        placeholder="e.g., Engine maintenance, Dock repair"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="eventDescription" class="form-label">Description</label>
                    <textarea 
                        id="eventDescription" 
                        name="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Additional details about the maintenance event..."
                    ></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate" class="form-label">Start Date *</label>
                        <input 
                            type="date" 
                            id="startDate" 
                            name="start_date" 
                            class="form-control" 
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="startTime" class="form-label">Start Time</label>
                        <input 
                            type="time" 
                            id="startTime" 
                            name="start_time" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="endDate" class="form-label">End Date</label>
                        <input 
                            type="date" 
                            id="endDate" 
                            name="end_date" 
                            class="form-control"
                        >
                    </div>
                    <div class="form-group">
                        <label for="endTime" class="form-label">End Time</label>
                        <input 
                            type="time" 
                            id="endTime" 
                            name="end_time" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="allDay" name="all_day" value="1">
                        <label for="allDay" class="form-label" style="margin-bottom: 0;">All Day Event</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="priority" class="form-label">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="LOW">Low</option>
                            <option value="MEDIUM" selected>Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assignedTo" class="form-label">Assigned To</label>
                        <select id="assignedTo" name="assigned_to" class="form-control">
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= h($user['full_name']) ?> (<?= h($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <input 
                        type="text" 
                        id="location" 
                        name="location" 
                        class="form-control" 
                        placeholder="e.g., Main dock, Engine room, Boat #123"
                    >
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="isRecurring" name="is_recurring" value="1">
                        <label for="isRecurring" class="form-label" style="margin-bottom: 0;">Recurring Event</label>
                    </div>
                </div>

                <div id="recurrenceOptions" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="recurrenceType" class="form-label">Repeat</label>
                            <select id="recurrenceType" name="recurrence_type" class="form-control">
                                <option value="DAILY">Daily</option>
                                <option value="WEEKLY">Weekly</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="YEARLY">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="recurrenceInterval" class="form-label">Every</label>
                            <input 
                                type="number" 
                                id="recurrenceInterval" 
                                name="recurrence_interval" 
                                class="form-control" 
                                min="1" 
                                value="1"
                            >
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="recurrenceEndDate" class="form-label">End Repeat (Optional)</label>
                        <input 
                            type="date" 
                            id="recurrenceEndDate" 
                            name="recurrence_end_date" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Attachments</label>
                    <div class="file-upload" onclick="document.getElementById('attachments').click()">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #6c757d; margin-bottom: 0.5rem;"></i>
                            <p>Click to upload files or drag and drop</p>
                            <p style="font-size: 0.8rem; color: #6c757d;">Supported: Images, PDFs, Documents (Max 5MB each)</p>
                        </div>
                    </div>
                    <input 
                        type="file" 
                        id="attachments" 
                        name="attachments[]" 
                        multiple 
                        style="display: none;"
                        accept="image/*,.pdf,.doc,.docx,.txt"
                    >
                    <div class="file-preview"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="saveEventBtn" onclick="saveEvent()">Save Event</button>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div id="eventDetailsModal" class="modal">
    <div class="modal-content event-details-enhanced">
        <div class="modal-header event-details-header">
            <h3 id="eventDetailsTitle">Event Details</h3>
            <button type="button" class="modal-close" onclick="closeEventDetailsModal()">&times;</button>
        </div>
        <div class="modal-body event-details-body" id="eventDetailsContent">
            <!-- Event details will be populated here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventDetailsModal()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" id="editEventBtn" onclick="editEvent()" style="display: none;">Edit</button>
            <button type="button" class="btn btn-success btn-sm" id="resolveEventBtn" onclick="showResolveModal()" style="display: none;">Mark as Complete</button>
            <button type="button" class="btn btn-danger btn-sm" id="deleteEventBtn" onclick="deleteEvent()" style="display: none;">Delete</button>
        </div>
    </div>
</div>

<!-- Resolution Modal -->
<div id="resolveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark Task as Complete</h3>
            <button type="button" class="modal-close" onclick="closeResolveModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="resolution-info">
                <p>You are about to mark this maintenance task as complete:</p>
                <div class="completed-event-summary" id="resolvedEventSummary">
                    <!-- Event title will be populated here -->
                </div>
                
                <form id="resolveForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" id="resolveEventId" name="event_id" value="">
                    
                    <div class="form-group">
                        <label for="resolutionNotes" class="form-label">Resolution Notes (Optional)</label>
                        <textarea 
                            id="resolutionNotes" 
                            name="resolution_notes" 
                            class="form-control" 
                            rows="4"
                            placeholder="Describe what was done to complete this task, any parts used, observations, etc..."
                        ></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Resolution Photos & Documents (Optional)</label>
                        <div class="file-upload" onclick="document.getElementById('resolutionFiles').click()">
                            <div class="upload-content">
                                <i class="fas fa-camera" style="font-size: 2rem; color: #6c757d; margin-bottom: 0.5rem;"></i>
                                <p>Upload photos, receipts, or documents</p>
                                <p style="font-size: 0.8rem; color: #6c757d;">Before/after photos, receipts, warranties, etc. (Max 5MB each)</p>
                            </div>
                        </div>
                        <input 
                            type="file" 
                            id="resolutionFiles" 
                            name="resolution_files[]" 
                            multiple 
                            style="display: none;"
                            accept="image/*,.pdf,.doc,.docx,.txt"
                        >
                        <div class="file-preview"></div>
                    </div>
                    
                    <div class="resolution-metadata">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Completed By</label>
                                <input type="text" class="form-control" value="<?= h($user['full_name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Resolution Date</label>
                                <input type="text" class="form-control" value="<?= date('Y-m-d H:i') ?>" readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeResolveModal()">Cancel</button>
            <button type="button" class="btn btn-success btn-sm" onclick="resolveEvent()">Mark as Complete</button>
        </div>
    </div>
</div>

<!-- Calendar Subscription Modal -->
<div id="subscriptionModal" class="modal">
    <div class="modal-content subscription-modal">
        <div class="modal-header subscription-header">
            <h3>Subscribe to Maintenance Calendar</h3>
            <button type="button" class="modal-close" onclick="closeSubscriptionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="subscription-intro">
                <p>Subscribe to the maintenance calendar to automatically receive updates in your personal calendar app. 
                Your calendar will sync with new events, changes, and cancellations automatically.</p>
            </div>
            
            <div class="subscription-options">
                <div class="subscription-option quick-setup">
                    <div class="option-header">
                        <h4>Quick Setup (Recommended)</h4>
                    </div>
                    <p>Click the button below to automatically open your default calendar app and add the subscription:</p>
                    <button type="button" class="btn btn-primary btn-large" onclick="subscribeToCalendar()" id="quickSubscribeBtn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                        Add to My Calendar
                    </button>
                </div>
                
                <div class="subscription-option manual-setup">
                    <div class="option-header">
                        <h4>Setup for Specific Apps</h4>
                    </div>
                    
                    <div class="calendar-tabs">
                        <button class="tab-btn active" onclick="showCalendarInstructions('google')">Google Calendar</button>
                        <button class="tab-btn" onclick="showCalendarInstructions('outlook')">Outlook</button>
                        <button class="tab-btn" onclick="showCalendarInstructions('apple')">Apple Calendar</button>
                        <button class="tab-btn" onclick="showCalendarInstructions('other')">Other Apps</button>
                    </div>
                    
                    <div class="calendar-instructions">
                        <!-- Google Calendar Instructions -->
                        <div id="instructions-google" class="instruction-content active">
                            <h5>Google Calendar Setup</h5>
                            <ol class="instruction-steps">
                                <li>Open <strong>Google Calendar</strong> in your browser</li>
                                <li>On the left sidebar, click the <strong>"+"</strong> next to "Other calendars"</li>
                                <li>Select <strong>"From URL"</strong></li>
                                <li>Paste the subscription URL below</li>
                                <li>Click <strong>"Add calendar"</strong></li>
                            </ol>
                        </div>
                        
                        <!-- Outlook Instructions -->
                        <div id="instructions-outlook" class="instruction-content">

                            <h5>Microsoft Outlook Setup</h5>
                            <ol class="instruction-steps">
                                <li>Open <strong>Outlook</strong> (desktop app or web)</li>
                                <li>Go to <strong>Calendar</strong> section</li>
                                <li>Click <strong>"Add calendar"</strong> → <strong>"Subscribe from web"</strong></li>
                                <li>Paste the subscription URL below</li>
                                <li>Give it a name like "PSC Maintenance"</li>
                                <li>Click <strong>"Import"</strong></li>
                            </ol>
                        </div>
                        
                        <!-- Apple Calendar Instructions -->
                        <div id="instructions-apple" class="instruction-content">

                            <h5>Apple Calendar Setup</h5>
                            <ol class="instruction-steps">
                                <li>Open <strong>Calendar</strong> app on Mac/iPhone/iPad</li>
                                <li>Go to <strong>File</strong> → <strong>"New Calendar Subscription"</strong> (Mac)</li>
                                <li>Or <strong>Settings</strong> → <strong>"Accounts"</strong> → <strong>"Add Account"</strong> → <strong>"Other"</strong> (iOS)</li>
                                <li>Paste the subscription URL below</li>
                                <li>Choose refresh frequency and click <strong>"Subscribe"</strong></li>
                            </ol>
                        </div>
                        
                        <!-- Other Apps Instructions -->
                        <div id="instructions-other" class="instruction-content">

                            <h5>Other Calendar Apps</h5>
                            <ol class="instruction-steps">
                                <li>Look for <strong>"Add calendar"</strong>, <strong>"Subscribe"</strong>, or <strong>"Import calendar"</strong> option</li>
                                <li>Choose <strong>"From URL"</strong> or <strong>"iCal subscription"</strong></li>
                                <li>Paste the subscription URL below</li>
                                <li>Save or import the calendar</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="url-section">
                        <label for="subscriptionUrl"><strong>Subscription URL:</strong></label>
                        <div class="url-input-group">
                            <input type="text" id="subscriptionUrl" class="form-control" readonly placeholder="Loading subscription URL...">
                            <button type="button" class="btn btn-secondary" onclick="copySubscriptionUrl()" id="copyBtn">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                </svg>
                                Copy URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="subscription-notes">
                <h5>Important Notes</h5>
                <ul>
                    <li>Your calendar will automatically update when maintenance events are added or changed</li>
                    <li>Updates may take a few hours depending on your calendar app's sync frequency</li>
                    <li>This subscription is read-only - you cannot edit events from your calendar app</li>
                    <li>The calendar includes all scheduled maintenance events with full details</li>
                </ul>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeSubscriptionModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Calendar functionality
let currentDate = new Date();
let currentView = 'month';
let selectedDate = null;
let currentEvent = null;
let events = [];
let isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
let currentUserId = <?= $_SESSION['user_id'] ?>;

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCalendar();
    loadEvents();
    setupEventListeners();
});

function setupEventListeners() {
    // File upload handling
    const fileInput = document.getElementById('attachments');
    const uploadArea = document.querySelector('.file-upload');

    if (fileInput && uploadArea) {
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        });
    }

    // Recurring event checkbox
    document.getElementById('isRecurring').addEventListener('change', function() {
        document.getElementById('recurrenceOptions').style.display = 
            this.checked ? 'block' : 'none';
    });

    // All day checkbox
    document.getElementById('allDay').addEventListener('change', function() {
        const timeInputs = ['startTime', 'endTime'];
        timeInputs.forEach(id => {
            document.getElementById(id).disabled = this.checked;
            if (this.checked) {
                document.getElementById(id).value = '';
            }
        });
    });

    // Resolution file upload handling
    const resolutionFileInput = document.getElementById('resolutionFiles');
    const resolutionUploadArea = document.querySelectorAll('.file-upload')[1]; // Second upload area

    if (resolutionFileInput && resolutionUploadArea) {
        resolutionFileInput.addEventListener('change', handleResolutionFileSelect);
        
        // Drag and drop for resolution files
        resolutionUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        resolutionUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        resolutionUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            resolutionFileInput.files = e.dataTransfer.files;
            handleResolutionFileSelect();
        });
    }

    // View controls
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentView = this.dataset.view;
            loadEvents(); // Load events for the new view's date range
        });
    });
}

function loadCalendar() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    if (currentView === 'month') {
        loadMonthView();
    } else if (currentView === 'week') {
        loadWeekView();
    } else {
        loadDayView();
    }
    
    // Add mobile-specific event listeners for better touch handling
    if (window.innerWidth <= 768) {
        // Improve touch scrolling for mobile
        const containers = grid.querySelectorAll('.week-container, .day-container');
        containers.forEach(container => {
            container.style.webkitOverflowScrolling = 'touch';
        });
    }
}

function loadMonthView() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    title.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    
    // Reset grid class and clear content
    grid.className = 'calendar-grid';
    grid.innerHTML = '';
    
    // Add day headers
    dayNames.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Get first day of month and number of days
    const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    const firstDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDayOfWeek; i++) {
        const prevDate = new Date(firstDay);
        prevDate.setDate(prevDate.getDate() - (firstDayOfWeek - i));
        const cell = createDayCell(prevDate, true);
        grid.appendChild(cell);
    }
    
    // Add days of current month
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
        const cell = createDayCell(date, false);
        grid.appendChild(cell);
    }
    
    // Add empty cells for days after month ends
    const totalCells = grid.children.length - 7; // Subtract headers
    const remainingCells = 42 - totalCells; // 6 weeks * 7 days
    for (let i = 1; i <= remainingCells; i++) {
        const nextDate = new Date(lastDay);
        nextDate.setDate(nextDate.getDate() + i);
        const cell = createDayCell(nextDate, true);
        grid.appendChild(cell);
    }
}

function createDayCell(date, isOtherMonth) {
    const cell = document.createElement('div');
    cell.className = `calendar-day${isOtherMonth ? ' other-month' : ''}`;
    cell.dataset.date = formatDate(date);
    
    // Check if this is today
    const today = new Date();
    const isToday = formatDate(date) === formatDate(today) && !isOtherMonth;
    
    const dayNumber = document.createElement('div');
    dayNumber.className = `day-number${isToday ? ' today' : ''}`;
    dayNumber.textContent = date.getDate();
    cell.appendChild(dayNumber);
    
    // Add events for this day
    const dayEvents = getEventsForDate(date);
    dayEvents.forEach(event => {
        const eventEl = document.createElement('div');
        let className = `calendar-event priority-${event.priority.toLowerCase()} status-${event.status.toLowerCase()}`;
        
        // Add completed styling if event is completed
        if (event.resolved_at) {
            className += ' status-resolved';
        }
        
        eventEl.className = className;
        eventEl.textContent = event.title;
        eventEl.onclick = (e) => {
            e.stopPropagation();
            showEventDetails(event);
        };
        cell.appendChild(eventEl);
    });
    
    cell.onclick = () => selectDate(date);
    
    return cell;
}

function getEventsForDate(date) {
    const dateStr = formatDate(date);
    return events.filter(event => {
        const eventDate = formatDate(new Date(event.start_datetime));
        return eventDate === dateStr;
    });
}

function getEventsForDateAndHour(date, hour) {
    const dateStr = formatDate(date);
    return events.filter(event => {
        const eventDateTime = new Date(event.start_datetime);
        const eventDate = formatDate(eventDateTime);
        const eventHour = eventDateTime.getHours();
        return eventDate === dateStr && eventHour === hour;
    });
}

function getEventsForDateAndTime(date, hour, minutes) {
    const dateStr = formatDate(date);
    return events.filter(event => {
        const eventDateTime = new Date(event.start_datetime);
        const eventDate = formatDate(eventDateTime);
        const eventHour = eventDateTime.getHours();
        const eventMinutes = eventDateTime.getMinutes();
        
        // Show events that start within this 30-minute window
        return eventDate === dateStr && eventHour === hour && 
               ((minutes === 0 && eventMinutes < 30) || (minutes === 30 && eventMinutes >= 30));
    });
}

function formatDate(date) {
    return date.getFullYear() + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
           String(date.getDate()).padStart(2, '0');
}

function selectDate(date) {
    // Remove previous selection
    document.querySelectorAll('.calendar-day.selected').forEach(cell => {
        cell.classList.remove('selected');
    });
    
    // Add selection to clicked cell
    const cell = document.querySelector(`[data-date="${formatDate(date)}"]`);
    if (cell) {
        cell.classList.add('selected');
        selectedDate = date;
    }
}

function navigateCalendar(direction) {
    if (direction === 'prev') {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() - 1);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() - 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() - 1);
        }
    } else if (direction === 'next') {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() + 1);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() + 1);
        }
    } else if (direction === 'today') {
        currentDate = new Date();
    }
    
    loadCalendar();
    loadEvents(); // Reload events for new period
}

function showAddEventModal(date = null) {
    currentEvent = null;
    document.getElementById('modalTitle').textContent = 'Add Maintenance Event';
    document.getElementById('editMode').value = '0';
    document.getElementById('eventForm').reset();
    document.querySelector('.file-preview').innerHTML = '';
    document.getElementById('recurrenceOptions').style.display = 'none';
    document.getElementById('saveEventBtn').textContent = 'Save Event';
    
    // Set default date if provided or selected
    if (date || selectedDate) {
        const defaultDate = date || selectedDate;
        document.getElementById('startDate').value = formatDate(defaultDate);
    }
    
    document.getElementById('eventModal').style.display = 'flex';
    
    // Focus on title
    setTimeout(() => {
        document.getElementById('eventTitle').focus();
    }, 100);
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function showEventDetails(event) {
    try {
        currentEvent = event;
        document.getElementById('eventDetailsTitle').textContent = event.title;
        
        const content = document.getElementById('eventDetailsContent');
        const startDate = new Date(event.start_datetime);
        const endDate = event.end_datetime ? new Date(event.end_datetime) : null;
        
        // Parse attachments
        let attachments = [];
        if (event.attachments) {
            try {
                attachments = typeof event.attachments === 'string' ? JSON.parse(event.attachments) : event.attachments;
                console.log('Parsed attachments:', attachments);
            } catch (e) {
                console.error('Error parsing attachments:', e);
                console.log('Raw attachments data:', event.attachments);
            }
        }
        
        // Parse resolution attachments
        let resolutionAttachments = [];
        if (event.resolution_attachments) {
            try {
                resolutionAttachments = typeof event.resolution_attachments === 'string' ? JSON.parse(event.resolution_attachments) : event.resolution_attachments;
                console.log('Parsed resolution attachments:', resolutionAttachments);
            } catch (e) {
                console.error('Error parsing resolution attachments:', e);
                console.log('Raw resolution attachments data:', event.resolution_attachments);
            }
        }
    
    content.innerHTML = `
        <div class="event-info-card">
            <div class="event-meta-grid">
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    <span class="meta-label">Start:</span>
                    <span class="meta-value">${formatDateTime(startDate)}</span>
                </div>
                ${endDate ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    <span class="meta-label">End:</span>
                    <span class="meta-value">${formatDateTime(endDate)}</span>
                </div>
                ` : ''}
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                    <span class="meta-label">Priority:</span>
                    <span class="priority-badge priority-${event.priority.toLowerCase()}">${event.priority}</span>
                </div>
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    <span class="meta-label">Status:</span>
                    <span class="status-badge status-${event.status.toLowerCase()}">${event.status}</span>
                </div>
                ${event.location ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span class="meta-label">Location:</span>
                    <span class="meta-value">${event.location}</span>
                </div>
                ` : ''}
                ${event.assigned_to_name ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span class="meta-label">Assigned:</span>
                    <span class="meta-value">${event.assigned_to_name}</span>
                </div>
                ` : ''}
            </div>
            
            ${event.description ? `
            <div class="event-description">
                <h4>
                    <svg style="width: 18px; height: 18px; vertical-align: -3px; margin-right: 6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10,9 9,9 8,9"></polyline>
                    </svg>
                    Description
                </h4>
                <p class="description-text">${event.description}</p>
            </div>
            ` : ''}
            
            ${attachments && attachments.length > 0 ? `
            <div class="event-attachments">
                <h4 class="attachments-header">
                    <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    Attachments (${attachments.length})
                </h4>
                <div class="attachments-grid">
                    ${attachments.map(file => {
                        const extension = file.extension || '';
                        const isImage = file.is_image || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension.toLowerCase());
                        const originalName = file.original_name || file.filename || 'Unknown file';
                        const filename = file.filename || '';
                        
                        return `
                        <a href="uploads/${filename}" target="_blank" class="attachment-item" title="${originalName}">
                            <div class="attachment-preview">
                                ${isImage ? 
                                    `<img src="uploads/${filename}" alt="${originalName}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                     <div class="attachment-icon" style="display: none;"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#767676"><path d="M9,2V7.38C8.83,7.79 8.67,8.21 8.5,8.62L9,9A5.5,5.5 0 0,1 14.5,14.5L15,15.12C15.29,15 15.58,14.85 15.85,14.69V2H9M21,9.5L18.38,12.13C18.45,12.37 18.5,12.62 18.5,12.88C18.5,14.18 17.45,15.23 16.15,15.23C15.89,15.23 15.64,15.18 15.4,15.11L12.75,17.76C12.82,18 12.87,18.26 12.87,18.53C12.87,19.83 11.82,20.88 10.52,20.88C9.22,20.88 8.17,19.83 8.17,18.53C8.17,17.23 9.22,16.18 10.52,16.18C10.78,16.18 11.04,16.23 11.28,16.3L13.93,13.65C13.86,13.41 13.81,13.15 13.81,12.88C13.81,11.58 14.86,10.53 16.16,10.53C16.42,10.53 16.67,10.58 16.91,10.65L19.5,8.06L21,9.5Z"/></svg></div>` :
                                    `<div class="attachment-icon">${getFileIcon(extension)}</div>`
                                }
                            </div>
                            <div class="attachment-name">${originalName}</div>
                        </a>
                        `;
                    }).join('')}
                </div>
            </div>
            ` : ''}
        </div>
        
        ${event.resolved_at ? `
        <div class="resolution-section">
            <div class="resolution-header">
                <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
                Task Completed
            </div>
            <div class="resolution-content">
                <div class="resolution-meta">
                    <div class="meta-item">
                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="meta-label">Completed by:</span>
                        <span class="meta-value">${event.resolved_by_name || 'Unknown'}</span>
                    </div>
                    <div class="meta-item">
                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                        <span class="meta-label">Completed on:</span>
                        <span class="meta-value">${formatDateTime(new Date(event.resolved_at))}</span>
                    </div>
                </div>
                
                ${event.resolution_notes ? `
                <div class="resolution-notes">
                    <h5>Completion Notes:</h5>
                    <p style="margin: 0; line-height: 1.5;">${event.resolution_notes}</p>
                </div>
                ` : ''}
                
                ${resolutionAttachments && resolutionAttachments.length > 0 ? `
                <div style="margin-top: 1rem;">
                    <h5 style="margin: 0 0 1rem 0; color: #155724; font-size: 0.9rem; font-weight: 600;">
                        Completion Photos/Files (${resolutionAttachments.length})
                    </h5>
                    <div class="attachments-grid">
                        ${resolutionAttachments.map(file => {
                            const extension = file.extension || '';
                            const isImage = file.is_image || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension.toLowerCase());
                            const originalName = file.original_name || file.filename || 'Unknown file';
                            const filename = file.filename || '';
                            
                            return `
                            <a href="uploads/${filename}" target="_blank" class="attachment-item" title="${originalName}">
                                <div class="attachment-preview">
                                    ${isImage ? 
                                        `<img src="uploads/${filename}" alt="${originalName}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                         <div class="attachment-icon" style="display: none;"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#767676"><path d="M9,2V7.38C8.83,7.79 8.67,8.21 8.5,8.62L9,9A5.5,5.5 0 0,1 14.5,14.5L15,15.12C15.29,15 15.58,14.85 15.85,14.69V2H9M21,9.5L18.38,12.13C18.45,12.37 18.5,12.62 18.5,12.88C18.5,14.18 17.45,15.23 16.15,15.23C15.89,15.23 15.64,15.18 15.4,15.11L12.75,17.76C12.82,18 12.87,18.26 12.87,18.53C12.87,19.83 11.82,20.88 10.52,20.88C9.22,20.88 8.17,19.83 8.17,18.53C8.17,17.23 9.22,16.18 10.52,16.18C10.78,16.18 11.04,16.23 11.28,16.3L13.93,13.65C13.86,13.41 13.81,13.15 13.81,12.88C13.81,11.58 14.86,10.53 16.16,10.53C16.42,10.53 16.67,10.58 16.91,10.65L19.5,8.06L21,9.5Z"/></svg></div>` :
                                        `<div class="attachment-icon">${getFileIcon(extension)}</div>`
                                    }
                                </div>
                                <div class="attachment-name">${originalName}</div>
                            </a>
                            `;
                        }).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
    `;
    
    // Show edit/delete/resolve buttons based on permissions and status
    const editBtn = document.getElementById('editEventBtn');
    const deleteBtn = document.getElementById('deleteEventBtn');
    const resolveBtn = document.getElementById('resolveEventBtn');
    
    const canEdit = isAdmin || (event.created_by == currentUserId);
    const canDelete = isAdmin || (event.created_by == currentUserId);
    const canResolve = (isAdmin || (event.created_by == currentUserId) || (event.assigned_to == currentUserId)) && 
                      (event.status !== 'COMPLETED' && !event.resolved_at);
    
    editBtn.style.display = canEdit ? 'inline-block' : 'none';
    deleteBtn.style.display = canDelete ? 'inline-block' : 'none';
    resolveBtn.style.display = canResolve ? 'inline-block' : 'none';
    
    document.getElementById('eventDetailsModal').style.display = 'flex';
    
    } catch (error) {
        console.error('Error in showEventDetails:', error);
        console.log('Event data:', event);
        alert('Error displaying event details. Check console for details.');
    }
}

function closeEventDetailsModal() {
    document.getElementById('eventDetailsModal').style.display = 'none';
}

function editEvent() {
    if (!currentEvent) return;
    
    // Check permissions
    const canEdit = isAdmin || (currentEvent.created_by == currentUserId);
    if (!canEdit) {
        alert('You can only edit events that you created.');
        return;
    }
    
    closeEventDetailsModal();
    
    // Populate form with event data
    document.getElementById('modalTitle').textContent = 'Edit Maintenance Event';
    document.getElementById('editMode').value = '1';
    document.getElementById('eventId').value = currentEvent.id;
    document.getElementById('eventTitle').value = currentEvent.title;
    document.getElementById('eventDescription').value = currentEvent.description || '';
    
    const startDate = new Date(currentEvent.start_datetime);
    document.getElementById('startDate').value = formatDate(startDate);
    document.getElementById('startTime').value = formatTime(startDate);
    
    if (currentEvent.end_datetime) {
        const endDate = new Date(currentEvent.end_datetime);
        document.getElementById('endDate').value = formatDate(endDate);
        document.getElementById('endTime').value = formatTime(endDate);
    }
    
    document.getElementById('allDay').checked = currentEvent.all_day == 1;
    document.getElementById('priority').value = currentEvent.priority;
    document.getElementById('assignedTo').value = currentEvent.assigned_to || '';
    document.getElementById('location').value = currentEvent.location || '';
    
    document.getElementById('isRecurring').checked = currentEvent.is_recurring == 1;
    if (currentEvent.is_recurring) {
        document.getElementById('recurrenceOptions').style.display = 'block';
        document.getElementById('recurrenceType').value = currentEvent.recurrence_type || 'WEEKLY';
        document.getElementById('recurrenceInterval').value = currentEvent.recurrence_interval || 1;
        document.getElementById('recurrenceEndDate').value = currentEvent.recurrence_end_date || '';
    }
    
    document.getElementById('saveEventBtn').textContent = 'Update Event';
    document.getElementById('eventModal').style.display = 'flex';
}

function deleteEvent() {
    if (!currentEvent) return;
    
    // Check permissions
    const canDelete = isAdmin || (currentEvent.created_by == currentUserId);
    if (!canDelete) {
        alert('You can only delete events that you created.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete the event "${currentEvent.title}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('event_id', currentEvent.id);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('maintenance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEventDetailsModal();
            loadEvents();
            alert(data.message || 'Event deleted successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to delete event'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the event');
    });
}

function showResolveModal() {
    if (!currentEvent) return;
    
    // Populate the event summary
    const summary = document.getElementById('resolvedEventSummary');
    summary.innerHTML = `
        <h4>${currentEvent.title}</h4>
        <p><strong>Location:</strong> ${currentEvent.location || 'Not specified'}</p>
        <p><strong>Priority:</strong> ${currentEvent.priority}</p>
        <p><strong>Assigned to:</strong> ${currentEvent.assigned_to_name || 'Unassigned'}</p>
    `;
    
    // Set the event ID
    document.getElementById('resolveEventId').value = currentEvent.id;
    
    // Clear previous data
    document.getElementById('resolutionNotes').value = '';
    const resolutionFileList = document.querySelectorAll('#resolveModal .file-preview')[0];
    if (resolutionFileList) {
        resolutionFileList.innerHTML = '';
    }
    
    // Close event details modal and show resolve modal
    closeEventDetailsModal();
    document.getElementById('resolveModal').style.display = 'flex';
    
    // Focus on notes field
    setTimeout(() => {
        document.getElementById('resolutionNotes').focus();
    }, 100);
}

function closeResolveModal() {
    document.getElementById('resolveModal').style.display = 'none';
}

function resolveEvent() {
    const form = document.getElementById('resolveForm');
    const formData = new FormData(form);
    formData.append('action', 'resolve');
    
    const resolveBtn = event.target;
    resolveBtn.disabled = true;
    resolveBtn.textContent = 'Resolving...';
    
    fetch('maintenance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeResolveModal();
            loadEvents();
            alert(data.message || 'Event marked as resolved successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to resolve event'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while resolving the event');
    })
    .finally(() => {
        resolveBtn.disabled = false;
        resolveBtn.textContent = 'Mark as Resolved';
    });
}

function getFileIcon(extension) {
    const ext = extension?.toLowerCase();
    switch (ext) {
        case 'pdf':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#dc3545"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
        case 'doc':
        case 'docx':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#2185d0"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
        case 'xls':
        case 'xlsx':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#21ba45"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
        case 'ppt':
        case 'pptx':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#f2711c"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
        case 'zip':
        case 'rar':
        case '7z':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#767676"><path d="M14,17H12V15H10V13H12V15H14M14,9H12V7H14M10,9H12V11H10M10,13H8V11H10M14,13H16V11H14M16,15H14V13H16M14,11H12V9H14V11M12,13H10V11H12V13M20,6H14L12,4H4A2,2 0 0,0 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8A2,2 0 0,0 20,6Z"/></svg>';
        case 'mp4':
        case 'avi':
        case 'mov':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#a333c8"><path d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>';
        case 'mp3':
        case 'wav':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#00b5ad"><path d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>';
        case 'txt':
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#767676"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
        default:
            return '<svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="#767676"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
    }
}

function formatDateTime(date) {
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function formatTime(date) {
    return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
}

function handleFileSelect() {
    const fileInput = document.getElementById('attachments');
    const fileList = document.querySelector('.file-preview');
    
    fileList.innerHTML = '';
    
    Array.from(fileInput.files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span>${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
            <button type="button" class="file-remove" onclick="removeFile(${index})">Remove</button>
        `;
        fileList.appendChild(fileItem);
    });
}

function removeFile(index) {
    const fileInput = document.getElementById('attachments');
    const dt = new DataTransfer();
    
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    handleFileSelect();
}

function handleResolutionFileSelect() {
    const fileInput = document.getElementById('resolutionFiles');
    const fileList = document.querySelectorAll('#resolveModal .file-preview')[0];
    
    if (!fileList) return;
    
    fileList.innerHTML = '';
    
    Array.from(fileInput.files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span>${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
            <button type="button" class="file-remove" onclick="removeResolutionFile(${index})">Remove</button>
        `;
        fileList.appendChild(fileItem);
    });
}

function removeResolutionFile(index) {
    const fileInput = document.getElementById('resolutionFiles');
    const dt = new DataTransfer();
    
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    handleResolutionFileSelect();
}

function saveEvent() {
    const form = document.getElementById('eventForm');
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    const saveBtn = document.getElementById('saveEventBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    fetch('maintenance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEventModal();
            loadEvents();
            alert(data.message || 'Event saved successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to save event'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the event');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = document.getElementById('editMode').value === '1' ? 'Update Event' : 'Save Event';
    });
}

function loadEvents() {
    let startDate, endDate;
    
    if (currentView === 'month') {
        // For month view, load the entire month
        startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    } else if (currentView === 'week') {
        // For week view, load the entire week
        startDate = new Date(currentDate);
        startDate.setDate(currentDate.getDate() - currentDate.getDay()); // Start of week (Sunday)
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6); // End of week (Saturday)
    } else if (currentView === 'day') {
        // For day view, load just the current day
        startDate = new Date(currentDate);
        endDate = new Date(currentDate);
    }
    
    const params = new URLSearchParams({
        action: 'list',
        start_date: formatDate(startDate),
        end_date: formatDate(endDate)
    });
    
    fetch('maintenance_api.php?' + params)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            events = data.events || [];
            console.log(`Loaded ${events.length} events for ${currentView} view from ${formatDate(startDate)} to ${formatDate(endDate)}`);
            console.log('Events:', events);
            loadCalendar(); // Refresh calendar with events
        } else {
            console.error('Failed to load events:', data.message);
            events = []; // Set empty events array
            loadCalendar(); // Still refresh calendar
        }
    })
    .catch(error => {
        console.error('Error loading events:', error);
        events = []; // Set empty events array on error
        loadCalendar(); // Still refresh calendar
    });
}

// Subscription functionality (simplified version)
function showSubscriptionModal() {
    const baseUrl = window.location.origin + window.location.pathname.replace('maintenance.php', '');
    const publicUrl = baseUrl + 'calendar_feed.php?token=public';
    const webcalUrl = publicUrl.replace(/^https?:\/\//, 'webcal://');
    
    document.getElementById('subscriptionUrl').value = webcalUrl;
    document.getElementById('subscriptionModal').style.display = 'flex';
}

function closeSubscriptionModal() {
    document.getElementById('subscriptionModal').style.display = 'none';
}

function subscribeToCalendar() {
    const url = document.getElementById('subscriptionUrl').value;
    if (!url) {
        alert('Subscription URL not available');
        return;
    }
    
    window.open(url, '_blank');
    
    setTimeout(() => {
        if (confirm('Did your calendar app open? If not, you can copy the URL and add it manually to your calendar application.')) {
            closeSubscriptionModal();
        }
    }, 2000);
}

function copySubscriptionUrl() {
    const urlInput = document.getElementById('subscriptionUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.background = '#28a745';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '';
        }, 2000);
        
    } catch (err) {
        alert('Could not copy URL. Please select and copy manually.');
    }
}

// Placeholder functions for week and day views
function loadWeekView() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    // Get start of week (Sunday)
    const startOfWeek = new Date(currentDate);
    startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
    
    // Format week title
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    const startMonth = monthNames[startOfWeek.getMonth()];
    const endMonth = monthNames[endOfWeek.getMonth()];
    
    if (startOfWeek.getMonth() === endOfWeek.getMonth()) {
        title.textContent = `${startMonth} ${startOfWeek.getDate()}-${endOfWeek.getDate()}, ${startOfWeek.getFullYear()}`;
    } else {
        title.textContent = `${startMonth} ${startOfWeek.getDate()} - ${endMonth} ${endOfWeek.getDate()}, ${startOfWeek.getFullYear()}`;
    }
    
    // Clear and setup grid for week view
    grid.innerHTML = '';
    grid.className = 'calendar-grid week-view';
    
    // Create week structure
    const weekContainer = document.createElement('div');
    weekContainer.className = 'week-container';
    
    // Create time column
    const timeColumn = document.createElement('div');
    timeColumn.className = 'time-column';
    
    // Add empty cell for header row
    const emptyHeader = document.createElement('div');
    emptyHeader.className = 'time-slot-header';
    timeColumn.appendChild(emptyHeader);
    
    // Add time slots (6 AM to 10 PM)
    for (let hour = 6; hour <= 22; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
        const ampm = hour >= 12 ? 'PM' : 'AM';
        timeSlot.textContent = `${displayHour}:00 ${ampm}`;
        timeColumn.appendChild(timeSlot);
    }
    
    weekContainer.appendChild(timeColumn);
    
    // Create day columns
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    for (let i = 0; i < 7; i++) {
        const dayDate = new Date(startOfWeek);
        dayDate.setDate(startOfWeek.getDate() + i);
        
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column';
        dayColumn.dataset.date = formatDate(dayDate);
        
        // Day header
        const dayHeader = document.createElement('div');
        dayHeader.className = 'day-header';
        const today = new Date();
        const isToday = formatDate(dayDate) === formatDate(today);
        
        dayHeader.innerHTML = `
            <div class="day-name">${dayNames[i]}</div>
            <div class="day-number ${isToday ? 'today' : ''}">${dayDate.getDate()}</div>
        `;
        dayColumn.appendChild(dayHeader);
        
        // Hour slots for this day
        for (let hour = 6; hour <= 22; hour++) {
            const hourSlot = document.createElement('div');
            hourSlot.className = 'hour-slot';
            hourSlot.dataset.hour = hour;
            hourSlot.dataset.date = formatDate(dayDate);
            
            // Add events for this hour
            const hourEvents = getEventsForDateAndHour(dayDate, hour);
            console.log(`Checking ${formatDate(dayDate)} hour ${hour}: found ${hourEvents.length} events`);
            hourEvents.forEach(event => {
                const eventEl = document.createElement('div');
                eventEl.className = `week-event priority-${event.priority.toLowerCase()} status-${event.status.toLowerCase()}`;
                if (event.resolved_at) {
                    eventEl.classList.add('status-resolved');
                }
                
                // Enhanced mobile display
                const eventDate = new Date(event.start_datetime);
                const eventHour = eventDate.getHours();
                const eventMinutes = eventDate.getMinutes();
                const displayHour = eventHour > 12 ? eventHour - 12 : eventHour === 0 ? 12 : eventHour;
                const ampm = eventHour >= 12 ? 'PM' : 'AM';
                const timeStr = `${displayHour}:${String(eventMinutes).padStart(2, '0')} ${ampm}`;
                
                // Check if we're on mobile
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    eventEl.innerHTML = `
                        <div style="font-weight: 600; margin-bottom: 2px;">${timeStr}</div>
                        <div style="margin-bottom: 2px;">${event.title}</div>
                        <div style="font-size: 0.8em; opacity: 0.9;">${event.priority} Priority</div>
                    `;
                } else {
                    eventEl.textContent = event.title;
                }
                
                eventEl.onclick = (e) => {
                    e.stopPropagation();
                    showEventDetails(event);
                };
                hourSlot.appendChild(eventEl);
            });
            
            // Allow clicking to create new events
            hourSlot.onclick = () => {
                selectedDate = new Date(dayDate);
                selectedDate.setHours(hour);
                // You can add logic here to pre-fill time when creating new events
            };
            
            dayColumn.appendChild(hourSlot);
        }
        
        weekContainer.appendChild(dayColumn);
    }
    
    grid.appendChild(weekContainer);
}

function loadDayView() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Format day title
    title.textContent = `${dayNames[currentDate.getDay()]}, ${monthNames[currentDate.getMonth()]} ${currentDate.getDate()}, ${currentDate.getFullYear()}`;
    
    // Clear and setup grid for day view
    grid.innerHTML = '';
    grid.className = 'calendar-grid day-view';
    
    // Create day structure
    const dayContainer = document.createElement('div');
    dayContainer.className = 'day-container';
    
    // Create time column
    const timeColumn = document.createElement('div');
    timeColumn.className = 'time-column';
    
    // Add day header
    const dayHeader = document.createElement('div');
    dayHeader.className = 'day-header-single';
    const today = new Date();
    const isToday = formatDate(currentDate) === formatDate(today);
    
    dayHeader.innerHTML = `
        <div class="day-info">
            <div class="day-name">${dayNames[currentDate.getDay()]}</div>
            <div class="day-number ${isToday ? 'today' : ''}">${currentDate.getDate()}</div>
        </div>
    `;
    
    // Create events column
    const eventsColumn = document.createElement('div');
    eventsColumn.className = 'events-column';
    eventsColumn.appendChild(dayHeader);
    
    // Add time slots with more granular intervals (every 30 minutes from 6 AM to 10 PM)
    for (let hour = 6; hour <= 22; hour++) {
        for (let minutes = 0; minutes < 60; minutes += 30) {
            // Time label (only show on hour marks)
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot-day';
            
            if (minutes === 0) {
                const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
                const ampm = hour >= 12 ? 'PM' : 'AM';
                timeSlot.textContent = `${displayHour}:00 ${ampm}`;
            }
            timeColumn.appendChild(timeSlot);
            
            // Event slot
            const eventSlot = document.createElement('div');
            eventSlot.className = 'event-slot-day';
            eventSlot.dataset.hour = hour;
            eventSlot.dataset.minutes = minutes;
            eventSlot.dataset.date = formatDate(currentDate);
            
            // Add events for this time slot
            const slotEvents = getEventsForDateAndTime(currentDate, hour, minutes);
            if (slotEvents.length > 0) {
                console.log(`Day view: Found ${slotEvents.length} events for ${formatDate(currentDate)} at ${hour}:${String(minutes).padStart(2, '0')}`);
            }
            slotEvents.forEach(event => {
                const eventEl = document.createElement('div');
                eventEl.className = `day-event priority-${event.priority.toLowerCase()} status-${event.status.toLowerCase()}`;
                if (event.resolved_at) {
                    eventEl.classList.add('status-resolved');
                }
                
                // Show more details in day view
                const eventDate = new Date(event.start_datetime);
                const eventHour = eventDate.getHours();
                const eventMinutes = eventDate.getMinutes();
                const displayHour = eventHour > 12 ? eventHour - 12 : eventHour === 0 ? 12 : eventHour;
                const ampm = eventHour >= 12 ? 'PM' : 'AM';
                const timeStr = `${displayHour}:${String(eventMinutes).padStart(2, '0')} ${ampm}`;
                
                eventEl.innerHTML = `
                    <div class="event-time">${timeStr}</div>
                    <div class="event-title">${event.title}</div>
                    <div class="event-priority">${event.priority}</div>
                `;
                
                eventEl.onclick = (e) => {
                    e.stopPropagation();
                    showEventDetails(event);
                };
                eventSlot.appendChild(eventEl);
            });
            
            // Allow clicking to create new events
            eventSlot.onclick = () => {
                selectedDate = new Date(currentDate);
                selectedDate.setHours(hour, minutes);
                // You can add logic here to pre-fill time when creating new events
            };
            
            eventsColumn.appendChild(eventSlot);
        }
    }
    
    dayContainer.appendChild(timeColumn);
    dayContainer.appendChild(eventsColumn);
    grid.appendChild(dayContainer);
}

// Enhanced subscription modal functions
function showCalendarInstructions(type) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.instruction-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    event.target.classList.add('active');
    document.getElementById('instructions-' + type).classList.add('active');
}

function subscribeToCalendar() {
    const url = document.getElementById('subscriptionUrl').value;
    if (!url) {
        alert('Subscription URL not available. Please try again.');
        return;
    }
    
    // Try to open the webcal URL
    window.open(url, '_blank');
    
    // Show a confirmation message
    setTimeout(() => {
        if (confirm('Did your calendar app open successfully? If not, you can copy the URL and add it manually using the instructions above.')) {
            closeSubscriptionModal();
        }
    }, 2000);
}

function copySubscriptionUrl() {
    const urlInput = document.getElementById('subscriptionUrl');
    const copyBtn = document.getElementById('copyBtn');
    
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        
        const originalContent = copyBtn.innerHTML;
        copyBtn.innerHTML = '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';
        copyBtn.style.background = '#28a745';
        copyBtn.style.borderColor = '#28a745';
        
        setTimeout(() => {
            copyBtn.innerHTML = originalContent;
            copyBtn.style.background = '';
            copyBtn.style.borderColor = '';
        }, 2000);
        
    } catch (err) {
        alert('Could not copy URL. Please select and copy manually.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
