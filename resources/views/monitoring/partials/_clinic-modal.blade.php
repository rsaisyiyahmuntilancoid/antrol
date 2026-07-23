<!-- CLINIC DETAIL MODAL -->
<div id="clinic-detail-modal"
    class="hidden fixed inset-0 z-[90] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div
        class="bg-white dark:bg-slate-950 rounded-3xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col border border-slate-200 dark:border-slate-800">
        <div
            class="flex justify-between items-center px-8 py-5 border-b border-slate-200 dark:border-slate-800 bg-blue-600/5 dark:bg-blue-900/10 rounded-t-3xl shrink-0">
            <div>
                <p class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-1">Detail Statistik Poliklinik
                </p>
                <h4 id="clinic-detail-title" class="text-lg font-bold text-slate-900 dark:text-white"></h4>
            </div>
            <button onclick="closeClinicModal()"
                class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                <i class="fas fa-times text-slate-500"></i>
            </button>
        </div>
        <div id="clinic-detail-content" class="overflow-y-auto p-6 flex-grow">
        </div>
    </div>
</div>
