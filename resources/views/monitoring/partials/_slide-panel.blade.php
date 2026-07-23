<!-- SLIDE-OVER DETAIL PANEL (AJAX LOADED) -->
<div id="slide-detail-panel"
    class="slide-panel fixed top-0 right-0 h-full w-full md:w-[480px] bg-white dark:bg-slate-950 shadow-2xl z-[80] translate-x-full border-l border-slate-200 dark:border-slate-800 flex flex-col">
    <!-- Header -->
    <div
        class="px-8 py-6 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-blue-600/5 dark:bg-blue-900/10">
        <div>
            <h3 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Detail Kunjungan & Timeline</h3>
            <p class="text-xs text-slate-400 mt-1">SIMRS vs BPJS Timestamp Analysis</p>
        </div>
        <button onclick="closeDetailPanel()"
            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Content Area (Scrollable) -->
    <div class="flex-grow overflow-y-auto p-8 space-y-6" id="panel-content">
        <!-- JS dynamically renders this -->
    </div>

    <!-- Panel Actions Footer -->
    <div class="p-6 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 flex justify-end gap-3"
        id="panel-footer">
        <!-- JS dynamically renders verify and cancel buttons -->
    </div>
</div>

<!-- Slide-over Backdrop Overlay -->
<div id="panel-overlay"
    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-[70] hidden transition-opacity opacity-0"
    onclick="closeDetailPanel()"></div>
