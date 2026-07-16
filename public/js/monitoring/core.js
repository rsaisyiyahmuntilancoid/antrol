/**
 * Core Monitoring Dashboard Module
 * Manages tab switching, initializations, and global state.
 */

let activeTab = 'simrs';

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCharts === 'function') {
        initCharts();
    }
    if (typeof setupFilters === 'function') {
        setupFilters();
    }
});

function switchTab(tabName) {
    activeTab = tabName;

    const tabSimrsBtn = document.getElementById('btn-tab-simrs');
    const tabBpjsBtn = document.getElementById('btn-tab-bpjs');
    const tabSimrsContent = document.getElementById('tab-simrs-content');
    const tabBpjsContent = document.getElementById('tab-bpjs-content');
    const simrsDateFilter = document.getElementById('simrs-date-filter');
    const bpjsDateFilter = document.getElementById('bpjs-dashboard-filter');

    if (tabName === 'simrs') {
        // UI Button states
        if (tabSimrsBtn) tabSimrsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400";
        if (tabBpjsBtn) tabBpjsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";

        // Content displays
        if (tabSimrsContent) tabSimrsContent.classList.remove('hidden');
        if (tabBpjsContent) tabBpjsContent.classList.add('hidden');
        if (simrsDateFilter) simrsDateFilter.classList.remove('hidden');
        if (bpjsDateFilter) bpjsDateFilter.classList.add('hidden');
    } else {
        // UI Button states
        if (tabBpjsBtn) tabBpjsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-teal-600 dark:text-teal-400";
        if (tabSimrsBtn) tabSimrsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";

        // Content displays
        if (tabBpjsContent) tabBpjsContent.classList.remove('hidden');
        if (tabSimrsContent) tabSimrsContent.classList.add('hidden');
        if (bpjsDateFilter) bpjsDateFilter.classList.remove('hidden');
        if (simrsDateFilter) simrsDateFilter.classList.add('hidden');
    }
}
