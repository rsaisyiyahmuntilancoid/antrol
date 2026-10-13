@extends('layouts.main')

@section('title', 'Task ID Logs')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <!-- Header -->
    <div class="glass rounded-3xl p-8 mb-8 space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white">Task ID Tracking</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2 flex items-center">
                    <i class="fas fa-microchip mr-2 text-indigo-600"></i>
                    Monitor and track BPJS Task ID updates and antrean additions
                </p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('regperiksa.index') }}"
                   class="glass px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Patients
                </a>
                <a href="{{ route('referensi.pendaftaran') }}"
                    class="glass px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center">
                    <i class="fas fa-file-alt mr-2"></i>Referensi MJKN
                </a>
                <a href="{{ route('bpjs-logs.index') }}"
                    class="bg-rose-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-rose-700 transition-all shadow-lg shadow-rose-500/20 flex items-center">
                    <i class="fas fa-list mr-2"></i>BPJS Logs
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <!-- Task Updates Stats -->
        <div class="lg:col-span-1 space-y-4">
             <div class="glass p-6 rounded-3xl border-indigo-500/10 bg-indigo-500/5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">Task ID Updates (Last 30 Days)</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">Success</span>
                        <span id="stat-task-success" class="font-bold text-emerald-500 text-lg"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">Failed</span>
                        <span id="stat-task-error" class="font-bold text-rose-500 text-lg"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Total</span>
                        <span id="stat-task-total" class="font-black text-indigo-600 text-xl"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                </div>
             </div>

             <div class="glass p-6 rounded-3xl border-blue-500/10 bg-blue-500/5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">Antrean Additions (Last 30 Days)</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">Success</span>
                        <span id="stat-antrean-success" class="font-bold text-emerald-500 text-lg"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">Failed</span>
                        <span id="stat-antrean-error" class="font-bold text-rose-500 text-lg"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Total</span>
                        <span id="stat-antrean-total" class="font-black text-blue-600 text-xl"><i class="fas fa-spinner fa-spin text-xs opacity-40"></i></span>
                    </div>
                </div>
             </div>
        </div>

        <!-- Main Content Area -->
        <div class="lg:col-span-3">
            <div class="glass rounded-3xl shadow-sm overflow-hidden flex flex-col h-full">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 flex flex-col md:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <button class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-600 text-white transition-all shadow-md" data-tab="taskid">Task Updates</button>
                        <button class="px-4 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 transition-all" data-tab="antrean">Antrean Records</button>
                    </div>

                    <div class="flex items-center gap-2">
                         <input type="date" id="startDate" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-1.5 text-xs outline-none">
                         <input type="date" id="endDate" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-1.5 text-xs outline-none">
                         <button id="filterBtn" class="bg-slate-900 text-white dark:bg-white dark:text-slate-900 px-3 py-1.5 rounded-lg text-xs font-bold hover:opacity-90">Filter</button>
                    </div>
                </div>

                <!-- Table Container (Task ID) -->
                <div id="taskid-tab" class="tab-content flex-grow overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/30 dark:bg-slate-800/20">
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Timestamp</th>
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Status</th>
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Booking Code</th>
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Task</th>
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Response</th>
                                <th class="px-8 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="taskid-logs-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <!-- JS Inject -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Placeholder -->
                <div class="px-8 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex justify-between items-center">
                    <span class="text-xs font-medium text-slate-400" id="taskid-showing-text">Showing 0 to 0 of 0</span>
                    <div class="flex gap-2">
                        <button id="taskid-prev-page" class="glass px-3 py-1 text-xs font-bold rounded-lg border-none hover:bg-slate-100 transition-all">Prev</button>
                        <button id="taskid-next-page" class="glass px-3 py-1 text-xs font-bold rounded-lg border-none hover:bg-slate-100 transition-all">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div id="logModal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="glass w-full max-w-5xl rounded-[40px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-10 py-8 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-indigo-600/5">
                <div>
                     <h3 class="text-2xl font-bold tracking-tight text-indigo-600 dark:text-indigo-400">Transmission Detail</h3>
                     <p class="text-sm text-slate-500 mt-1" id="modalBookingLabel">Booking Code: ---</p>
                </div>
                <button id="closeModal" class="w-10 h-10 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors border-none">
                    <i class="fas fa-times text-slate-400"></i>
                </button>
            </div>
            
            <div class="p-10 overflow-y-auto space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                     <div class="space-y-3">
                         <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 ml-1">Request Payload</label>
                         <pre id="modalRequest" class="bg-white/50 dark:bg-slate-900/50 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 text-xs font-mono leading-relaxed overflow-x-auto"></pre>
                     </div>
                     <div class="space-y-3">
                         <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 ml-1">API Response</label>
                         <pre id="modalResponse" class="bg-white/50 dark:bg-slate-900/50 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 text-xs font-mono leading-relaxed overflow-x-auto"></pre>
                     </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <div class="glass p-4 rounded-2xl text-center">
                        <p class="text-[9px] uppercase font-bold text-slate-400 mb-1">Status Code</p>
                        <p class="font-bold text-indigo-600" id="modalCode">---</p>
                    </div>
                    <div class="glass p-4 rounded-2xl text-center">
                        <p class="text-[9px] uppercase font-bold text-slate-400 mb-1">Method</p>
                        <p class="font-bold text-slate-600" id="modalMethod">---</p>
                    </div>
                    <div class="glass p-4 rounded-2xl text-center">
                        <p class="text-[9px] uppercase font-bold text-slate-400 mb-1">Task Level</p>
                        <p class="font-bold text-slate-600" id="modalTaskId">---</p>
                    </div>
                     <div class="glass p-4 rounded-2xl text-center">
                        <p class="text-[9px] uppercase font-bold text-slate-400 mb-1">Clocked At</p>
                        <p class="font-bold text-slate-600 text-[10px]" id="modalTime">---</p>
                    </div>
                </div>
            </div>

            <div class="px-10 py-8 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-900/50">
                 <button id="closeModalBtn" class="glass px-6 py-3 rounded-2xl text-sm font-bold text-slate-500 hover:bg-white transition-all">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Global State
    const state = {
        currentTab: 'taskid',
        taskid: { page: 1, perPage: 25, startDate: null, endDate: null },
        antrean: { page: 1, perPage: 25, startDate: null, endDate: null }
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        loadStats();
        loadLogs();
        setupFilters();
        setupModalEvents();
    });

    function initTabs() {
        const btns = document.querySelectorAll('[data-tab]');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                state.currentTab = tab;
                
                // Update UI
                btns.forEach(b => {
                    b.classList.remove('bg-indigo-600', 'text-white', 'shadow-md');
                    b.classList.add('text-slate-500', 'hover:bg-slate-100');
                });
                btn.classList.add('bg-indigo-600', 'text-white', 'shadow-md');
                btn.classList.remove('text-slate-500', 'hover:bg-slate-100');
                
                loadLogs();
            });
        });
    }

    function loadStats(startDate = null, endDate = null) {
        let url = '/api/v1/mobilejkn/task-id-stats';
        const params = [];
        if (startDate) params.push(`date_from=${encodeURIComponent(startDate)}`);
        if (endDate) params.push(`date_to=${encodeURIComponent(endDate)}`);
        if (params.length > 0) url += '?' + params.join('&');

        axios.get(url).then(res => {
            const d = res.data?.data || {};
            document.getElementById('stat-task-success').textContent = Number(d.task_success || 0).toLocaleString();
            document.getElementById('stat-task-error').textContent = Number(d.task_error || 0).toLocaleString();
            document.getElementById('stat-task-total').textContent = Number(d.task_total || 0).toLocaleString();
            document.getElementById('stat-antrean-success').textContent = Number(d.antrean_success || 0).toLocaleString();
            document.getElementById('stat-antrean-error').textContent = Number(d.antrean_error || 0).toLocaleString();
            document.getElementById('stat-antrean-total').textContent = Number(d.antrean_total || 0).toLocaleString();
        }).catch(err => {
            console.error('Failed to load stats', err);
            document.getElementById('stat-task-success').textContent = '-';
            document.getElementById('stat-task-error').textContent = '-';
            document.getElementById('stat-task-total').textContent = '-';
            document.getElementById('stat-antrean-success').textContent = '-';
            document.getElementById('stat-antrean-error').textContent = '-';
            document.getElementById('stat-antrean-total').textContent = '-';
        });
    }

    function setupFilters() {
        document.getElementById('filterBtn').onclick = () => {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            state[state.currentTab].startDate = start;
            state[state.currentTab].endDate = end;
            state[state.currentTab].page = 1;
            loadLogs();
            loadStats(start, end);
        };

        document.getElementById('taskid-prev-page').onclick = () => { if(state.taskid.page > 1) { state.taskid.page--; loadLogs(); } };
        document.getElementById('taskid-next-page').onclick = () => { state.taskid.page++; loadLogs(); };
    }

    function loadLogs() {
        const tab = state.currentTab;
        const tbody = document.getElementById('taskid-logs-body');
        
        tbody.innerHTML = `<tr><td colspan="6" class="py-20 text-center"><div class="inline-block w-8 h-8 border-4 border-indigo-600/20 border-t-indigo-600 rounded-full animate-spin"></div></td></tr>`;
        
        let url = `/api/v1/mobilejkn/${tab == 'taskid' ? 'task-id-logs' : 'antrean-logs'}?page=${state[tab].page}&perPage=${state[tab].perPage}`;
        if(state[tab].startDate && state[tab].endDate && tab == 'taskid') {
             url = `/api/v1/mobilejkn/filtered-task-id-logs?startDate=${state[tab].startDate}&endDate=${state[tab].endDate}&page=${state[tab].page}&perPage=${state[tab].perPage}`;
        }

        axios.get(url).then(res => {
            renderLogs(res.data);
            updatePaginationInfo(res.data);
        }).catch(e => {
            tbody.innerHTML = `<tr><td colspan="6" class="py-20 text-center text-rose-500 font-bold">Failed to connect to monitoring service</td></tr>`;
        });
    }

    function renderLogs(data) {
        const tbody = document.getElementById('taskid-logs-body');
        tbody.innerHTML = '';
        
        if(!data.data || data.data.length == 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-20 text-center text-slate-400">No transmission logs found</td></tr>`;
            return;
        }

        data.data.forEach(log => {
            const req = tryParse(log.request);
            const res = log.response ? tryParse(log.response) : tryParse(log.message);
            const isSuccess = log.code >= 200 && log.code < 300;
            const metaMessage = log.bpjs_meta_message || res?.metadata?.message || res?.metaData?.message || '-';
            const bookingCode = req?.kodebooking || log.kodebooking || '---';
            const taskIdDisplay = req?.taskid || (log.operation === 'add_antrean' ? 'ADD' : (log.operation || 'ANT'));
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors group';
            tr.innerHTML = `
                <td class="px-8 py-5 text-xs font-semibold text-slate-500">${new Date(log.created_at).toLocaleString()}</td>
                <td class="px-8 py-5">
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold ${isSuccess ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">
                        ${log.code}
                    </span>
                </td>
                <td class="px-8 py-5 font-mono text-xs font-bold text-slate-700 dark:text-slate-300">
                    <div>${bookingCode}</div>
                    ${log.norm ? `<div class="text-[10px] text-slate-400 font-normal">RM: ${log.norm}</div>` : ''}
                </td>
                <td class="px-8 py-5">
                    <span class="text-xs font-black text-indigo-600">${taskIdDisplay}</span>
                </td>
                <td class="px-8 py-5">
                    <div class="text-[11px] text-slate-500 truncate max-w-[200px]" title="${metaMessage}">${metaMessage}</div>
                </td>
                <td class="px-8 py-5 text-right">
                    <button class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 hover:text-indigo-800 transition-colors flex items-center justify-end gap-1 ml-auto" onclick='openModal(${JSON.stringify(log)})'>
                        Detail <i class="fas fa-arrow-right"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updatePaginationInfo(data) {
        document.getElementById('taskid-showing-text').textContent = `Showing ${data.from || 0} to ${data.to || 0} of ${data.total || 0}`;
        document.getElementById('taskid-prev-page').disabled = data.current_page <= 1;
        document.getElementById('taskid-next-page').disabled = data.current_page >= data.last_page;
    }

    function openModal(log) {
        const req = tryParse(log.request);
        const res = log.response ? tryParse(log.response) : tryParse(log.message);
        
        document.getElementById('modalRequest').textContent = JSON.stringify(req, null, 4);
        document.getElementById('modalResponse').textContent = JSON.stringify(res, null, 4);
        document.getElementById('modalCode').textContent = log.code;
        document.getElementById('modalMethod').textContent = log.method || 'POST';
        document.getElementById('modalTaskId').textContent = req?.taskid || (log.operation || 'N/A');
        document.getElementById('modalTime').textContent = new Date(log.created_at).toLocaleString();
        
        let label = `Booking Code: ${req?.kodebooking || log.kodebooking || 'N/A'}`;
        if (log.norm) label += ` | RM: ${log.norm}`;
        if (log.nokartu) label += ` | Kartu: ${log.nokartu}`;
        if (log.operation) label += ` | Op: ${log.operation}`;
        document.getElementById('modalBookingLabel').textContent = label;
        
        document.getElementById('logModal').classList.remove('hidden');
    }

    function setupModalEvents() {
        const close = () => document.getElementById('logModal').classList.add('hidden');
        document.getElementById('closeModal').onclick = close;
        document.getElementById('closeModalBtn').onclick = close;
    }

    function tryParse(s) { try{ return typeof s === 'string' ? JSON.parse(s) : s; }catch(e){ return null; } }
</script>
@endpush
