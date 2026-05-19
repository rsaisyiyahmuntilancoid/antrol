@extends('layouts.main')

@section('title', 'Run Command')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    <!-- Header -->
    <div class="glass rounded-3xl p-8 mb-8 space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white">Batch Processor</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2 flex items-center">
                    <i class="fas fa-terminal mr-2 text-amber-600"></i>
                    Execute automated Task ID sequences for specific date ranges
                </p>
            </div>
            
            <a href="{{ route('regperiksa.index') }}"
               class="glass px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Patients
            </a>
            
            <a href="{{ route('log.viewer') }}"
               class="glass px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center" target="_blank">
                <i class="fas fa-stream mr-2"></i>View Logs
            </a>

            <a id="executionDetailsBtn" href="javascript:void(0)"
               class="glass px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center" style="display: none;">
                <i class="fas fa-chart-line mr-2"></i>Execution Details
            </a>
        </div>
    </div>

    <!-- Main Tool -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Configuration & Task IDs -->
        <div class="lg:col-span-1 flex flex-col gap-8">
            <!-- Configuration Panel -->
            <div class="glass rounded-3xl p-8 shadow-sm space-y-8">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-cog mr-3 text-amber-500"></i> Configuration
                </h3>

                <!-- Queue Alert -->
                <div id="queueHelperAlert" class="hidden p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/20">
                    <div class="flex gap-3">
                        <i class="fas fa-exclamation-triangle text-amber-600 mt-1"></i>
                        <div class="text-[11px] text-amber-800 dark:text-amber-400 font-medium">
                            <strong>Queue workers might not be running.</strong> 
                            Run <code class="bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 rounded">php artisan queue:work</code> in terminal.
                        </div>
                    </div>
                </div>

                <form id="commandForm" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 ml-1">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="{{ date('Y-m-d') }}"
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-3 font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 ml-1">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="{{ date('Y-m-d') }}"
                               class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-3 font-semibold text-slate-700 focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                    </div>

                    <div class="flex items-center gap-3 p-4 glass rounded-2xl">
                        <div class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 dark:bg-slate-800 transition-colors pointer-events-none">
                            <input type="checkbox" id="dry_run" name="dry_run" class="sr-only peer pointer-events-auto">
                            <div class="peer-checked:bg-amber-600 absolute inset-0 rounded-full transition-colors"></div>
                            <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5 shadow-sm"></span>
                        </div>
                        <label for="dry_run" class="text-xs font-bold text-slate-500 cursor-pointer">Dry Run Mode</label>
                    </div>
                    
                    <div class="flex items-center gap-3 p-4 glass rounded-2xl">
                        <div class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 dark:bg-slate-800 transition-colors pointer-events-none">
                            <input type="checkbox" id="mjkn_only" name="mjkn_only" class="sr-only peer pointer-events-auto">
                            <div class="peer-checked:bg-indigo-600 absolute inset-0 rounded-full transition-colors"></div>
                            <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5 shadow-sm"></span>
                        </div>
                        <label for="mjkn_only" class="text-xs font-bold text-slate-500 cursor-pointer">M-JKN App Only</label>
                    </div>

                    <div class="flex items-center gap-3 p-4 glass rounded-2xl">
                        <div class="relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 dark:bg-slate-800 transition-colors pointer-events-none">
                            <input type="checkbox" id="all_patients" name="all_patients" class="sr-only peer pointer-events-auto">
                            <div class="peer-checked:bg-emerald-600 absolute inset-0 rounded-full transition-colors"></div>
                            <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5 shadow-sm"></span>
                        </div>
                        <label for="all_patients" class="text-xs font-bold text-slate-500 cursor-pointer">Resend All Patients</label>
                    </div>

                    <button type="submit" class="w-full bg-slate-900 dark:bg-white dark:text-slate-900 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:opacity-90 shadow-xl transition-all">
                        Execute Sequence
                    </button>
                </form>
            </div>

            <!-- Task IDs Panel -->
            <div class="glass rounded-3xl p-8 shadow-sm space-y-4 flex-1 flex flex-col">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold flex items-center">
                        <i class="fas fa-tasks mr-3 text-blue-500"></i> Task IDs Queue
                    </h3>
                    <button type="button" id="reloadTaskIds" class="text-blue-500 hover:text-blue-600 transition-colors" title="Reload task IDs">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                
                <div id="taskIdsList" class="flex-1 overflow-y-auto space-y-2 bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4">
                    <div class="text-slate-400 italic text-center py-8">
                        <i class="fas fa-info-circle mr-2"></i>Task IDs will appear here...
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Real-time Log Output -->
        <div class="lg:col-span-2">
            <div class="glass h-[600px] rounded-[40px] shadow-2xl flex flex-col overflow-hidden border-slate-900/5 dark:border-white/5">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-900">
                    <div class="flex items-center gap-3">
                        <div id="statusIndicator" class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                        <span id="statusText" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Terminal Offline</span>
                    </div>
                    <button id="stopButton" class="hidden px-4 py-1.5 rounded-lg bg-rose-500 text-white text-[10px] font-bold uppercase transition-all hover:bg-rose-600">
                        Kill Process
                    </button>
                </div>
                
                <div id="outputArea" class="flex-grow bg-slate-900 p-8 font-mono text-[11px] leading-relaxed text-emerald-500/90 overflow-y-auto whitespace-pre-wrap selection:bg-emerald-500/20">
                    <div class="text-slate-500 italic flex flex-col items-center justify-center h-full space-y-4">
                        <i class="fas fa-terminal text-4xl opacity-10"></i>
                        <span>Waiting for command execution...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let jobId = null;
    let outputInterval = null;
    
    // Load task IDs when dates change
    function loadTaskIds() {
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;
        
        if (!dateFrom || !dateTo) return;
        
        console.log('Loading task IDs for:', dateFrom, 'to', dateTo);
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        fetch(`{{ route('command.task-ids') }}?date_from=${dateFrom}&date_to=${dateTo}`, {
            signal: controller.signal
        })
        .then(r => {
            clearTimeout(timeoutId);
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            console.log('Task IDs loaded:', data.task_ids);
            displayTaskIds(data.task_ids);
        })
        .catch(err => {
            clearTimeout(timeoutId);
            console.error('Failed to load task IDs:', err);
            const container = document.getElementById('taskIdsList');
            if (err.name === 'AbortError') {
                container.innerHTML = '<div class="text-amber-400 italic text-center py-8"><i class="fas fa-clock mr-2"></i>Load timeout - try again</div>';
            } else {
                container.innerHTML = '<div class="text-slate-400 italic text-center py-8"><i class="fas fa-exclamation-circle mr-2"></i>Failed to load task IDs</div>';
            }
        });
    }

    // Display task IDs in the left panel
    function displayTaskIds(taskIds) {
        const container = document.getElementById('taskIdsList');
        
        if (!taskIds || taskIds.length === 0) {
            container.innerHTML = '<div class="text-slate-400 italic text-center py-8"><i class="fas fa-info-circle mr-2"></i>No task IDs found</div>';
            return;
        }

        let html = '';
        taskIds.forEach((taskId, index) => {
            const statusClass = taskId <= 4 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300';
            const icon = taskId === 5 ? 'fa-star' : 'fa-circle';
            const label = taskId === 5 ? 'Auto-Generated' : `Standard`;
            
            html += `
                <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 hover:shadow-md transition-all">
                    <div class="flex items-center gap-3">
                        <i class="fas ${icon} text-sm ${taskId === 5 ? 'text-amber-500' : 'text-slate-400'}"></i>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Task ID ${taskId}</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400">${label}</div>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-semibold ${statusClass}">
                        ${taskId === 5 ? 'Generated' : taskId <= 3 ? 'Pending' : 'Ready'}
                    </span>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }

    // Load task IDs on page load and when dates change
    document.getElementById('date_from').addEventListener('change', loadTaskIds);
    document.getElementById('date_to').addEventListener('change', loadTaskIds);
    document.getElementById('reloadTaskIds').addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Manually reloading task IDs...');
        loadTaskIds();
    });
    
    // Load initial task IDs
    loadTaskIds();
    
    document.getElementById('commandForm').onsubmit = (e) => {
        e.preventDefault();
        
        const payload = {
            date_from: document.getElementById('date_from').value,
            date_to: document.getElementById('date_to').value,
            dry_run: document.getElementById('dry_run').checked,
            mjkn: document.getElementById('mjkn_only').checked,
            all: document.getElementById('all_patients').checked
        };
        
        const output = document.getElementById('outputArea');
        output.innerHTML = `<span class="text-white font-bold animate-pulse">Initializing pipeline...</span>\n`;
        output.innerHTML += `<span class="text-slate-500">[debug]</span> Sending request to server...\n`;
        
        console.log('Form submitted with payload:', payload);
        console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]')?.content || 'NOT FOUND');
        console.log('Route URL:', '{{ route("command.run") }}');
        
        document.getElementById('stopButton').classList.remove('hidden');
        updateStatus('running');

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout

        fetch('{{ route("command.run") }}', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload),
            signal: controller.signal
        })
        .then(async r => {
            clearTimeout(timeoutId);
            console.log('Response received:', r.status, r.statusText);
            
            const data = await r.json();
            console.log('Response data:', data);
            
            if (!r.ok) {
                throw new Error(data.error || `HTTP ${r.status}: ${r.statusText}`);
            }
            return data;
        })
        .then(data => {
            console.log('Success! Job ID:', data.job_id);
            jobId = data.job_id;
            
            // Show execution details button
            const executionBtn = document.getElementById('executionDetailsBtn');
            executionBtn.href = `/execution-viewer/${jobId}`;
            executionBtn.style.display = 'flex';
            executionBtn.target = '_blank';
            
            output.innerHTML = `<span class="text-emerald-400">[✓]</span> Job dispatched successfully\n`;
            output.innerHTML += `<span class="text-emerald-400">[info]</span> Job ID: ${jobId}\n`;
            outputInterval = setInterval(fetchOutput, 1000);
            if (data.queue_info?.message) output.innerHTML += `<span class="text-blue-400">[info]</span> ${data.queue_info.message}\n`;
        })
        .catch(err => {
            clearTimeout(timeoutId);
            console.error('Failed to start sync engine:', err);
            
            output.innerHTML = `<span class="text-rose-500">[✗]</span> Failed to start sync engine\n`;
            
            if (err.name === 'AbortError') {
                output.innerHTML += `<span class="text-rose-400">[error]</span> Request timeout (60s) - server may not be responding\n`;
            } else {
                output.innerHTML += `<span class="text-rose-400">[error]</span> ${err.message}\n`;
            }
            
            output.innerHTML += `<span class="text-amber-400">[debug]</span> Check browser console (F12) for more details\n`;
            console.error('Full error:', err);
            updateStatus('failed');
        });
    };

    function fetchOutput() {
        if (!jobId) return;
        
        fetch(`{{ route('command.output', ['jobId' => ':jobId']) }}`.replace(':jobId', jobId))
            .then(r => r.json())
            .then(data => {
                const area = document.getElementById('outputArea');
                
                // Colorize the output
                if (data.output) {
                    area.textContent = data.output.join('').replace(/✓/g, '✔').replace(/✗/g, '✘');
                    area.scrollTop = area.scrollHeight;
                }

                if (data.status === 'completed') {
                    updateStatus('completed');
                    clearInterval(outputInterval);
                    document.getElementById('stopButton').classList.add('hidden');
                } else if (data.status === 'failed') {
                    updateStatus('failed');
                    clearInterval(outputInterval);
                } else if (data.status === 'stopped') {
                    updateStatus('stopped');
                    clearInterval(outputInterval);
                }

                if (data.queue_status?.queue_status === 'no_workers') {
                    document.getElementById('queueHelperAlert').classList.remove('hidden');
                }
            });
    }

    function updateStatus(stts) {
        const ind = document.getElementById('statusIndicator');
        const txt = document.getElementById('statusText');
        const colors = {
            running: 'bg-amber-500 animate-pulse',
            completed: 'bg-emerald-500',
            failed: 'bg-rose-500',
            stopped: 'bg-slate-500'
        };
        const labels = {
            running: 'Processing Batch',
            completed: 'Sync Finished',
            failed: 'Process Halted',
            stopped: 'Process Killed'
        };
        
        ind.className = `w-2.5 h-2.5 rounded-full ${colors[stts]}`;
        txt.textContent = labels[stts];
    }

    document.getElementById('stopButton').onclick = () => {
        if (!jobId) return;
        fetch(`{{ route('command.stop') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ job_id: jobId })
        });
    };
</script>
@endpush
