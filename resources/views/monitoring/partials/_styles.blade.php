@push('styles')
<style>
    /* CSS slide-panel transition handles slide-in animation */
    .slide-panel {
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .slide-panel.open {
        transform: translateX(0);
    }

    /* Animation pulse for duration badges */
    .flow-duration {
        animation: duration-pulse 3s infinite ease-in-out;
    }

    @keyframes duration-pulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.9;
            transform: scale(1.03);
        }
    }

    /* Vertical line for patient chronological list */
    .patient-timeline-line {
        position: absolute;
        left: 20px;
        top: 24px;
        bottom: 24px;
        width: 2px;
    }

    /* Status Badge Styling ala ANT-1 */
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 9999px;
        letter-spacing: 0.025em;
        text-transform: uppercase;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .status-badge-green {
        background-color: #ecfdf5;
        color: #059669;
    }

    .dark .status-badge-green {
        background-color: rgba(16, 185, 129, 0.15);
        color: #34d399;
    }

    .status-badge-blue {
        background-color: #eff6ff;
        color: #2563eb;
    }

    .dark .status-badge-blue {
        background-color: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
    }

    .status-badge-purple {
        background-color: #faf5ff;
        color: #7c3aed;
    }

    .dark .status-badge-purple {
        background-color: rgba(139, 92, 246, 0.15);
        color: #a78bfa;
    }

    .status-badge-amber {
        background-color: #fffbeb;
        color: #d97706;
    }

    .dark .status-badge-amber {
        background-color: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
    }

    .status-badge-rose {
        background-color: #fff1f2;
        color: #e11d48;
    }

    .dark .status-badge-rose {
        background-color: rgba(244, 63, 94, 0.15);
        color: #fb7185;
    }

    .status-badge-slate {
        background-color: #f1f5f9;
        color: #475569;
    }

    .dark .status-badge-slate {
        background-color: rgba(148, 163, 184, 0.12);
        color: #94a3b8;
    }

    @media print {
        /* Set page size to A4 Portrait with standard margins */
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 20mm 15mm;
        }

        /* Prevent blank/clipped pages by resetting heights & positions on wrappers */
        html, body, .h-full, .min-h-full, main, .max-w-\[1600px\],
        #tab-simrs-content, #tab-bpjs-content, .flex-grow {
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            position: static !important;
            background-color: white !important;
            color: black !important;
        }

        /* Hide screen-only interactive controls */
        nav, footer,
        #btn-tab-simrs, #btn-tab-bpjs,
        .flex.bg-slate-100,
        #simrs-date-filter, #bpjs-dashboard-filter,
        #sync-progress-container, #range-sync-banner,
        .glass.rounded-3xl.p-8.mb-8.space-y-6,
        #patient-registry-card > div.border-b > div.flex-wrap,
        .pagination-controls,
        #bpjs-report-container > div.bg-slate-50\/50 > div,
        #bpjs-pagination-controls,
        button, select, input {
            display: none !important;
        }

        /* Layout stretching for printing paper */
        main, .max-w-\[1600px\] {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }

        /* Redesign glass widgets to flat, bordered panels */
        .glass {
            background-color: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            padding: 12px !important;
            margin-bottom: 1.5rem !important;
            page-break-inside: avoid !important;
        }

        .glass h3 {
            color: #475569 !important;
            font-size: 10px !important;
            text-transform: uppercase !important;
        }

        .glass p {
            color: #0f172a !important;
            font-size: 1.5rem !important;
            font-weight: 800 !important;
            margin-top: 4px !important;
        }

        /* Hide icons on KPI cards in print mode */
        .glass > div[class*="absolute"] {
            display: none !important;
        }

        /* Ensure charts and indicators render colors cleanly */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-scheme: light !important;
        }

        /* Hide non-active tab */
        #tab-simrs-content.hidden, #tab-bpjs-content.hidden {
            display: none !important;
        }

        /* Prevent scrollbars on list tables */
        .overflow-x-auto, .overflow-y-auto {
            overflow: visible !important;
            max-h-none !important;
            max-height: none !important;
        }

        /* Professional table layouts with borders */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 10px !important;
            margin-bottom: 20px !important;
        }

        table, tr, td, th {
            page-break-inside: avoid !important;
        }

        table th {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 9px !important;
            border: 1px solid #94a3b8 !important;
            padding: 6px 4px !important;
        }

        table td {
            color: #1e293b !important;
            border: 1px solid #cbd5e1 !important;
            padding: 6px 4px !important;
            font-size: 9px !important;
        }

        /* Hide Action columns (last column of tables) */
        table th:last-child, table td:last-child {
            display: none !important;
        }

        /* Compact Chart rendering */
        canvas {
            max-width: 100% !important;
            max-height: 220px !important;
        }

        /* Page layout grid adjustments */
        .grid {
            display: grid !important;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
        }

        .lg\:grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        .lg\:col-span-2 {
            grid-column: span 2 / span 2 !important;
        }

        .lg\:col-span-1 {
            grid-column: span 1 / span 1 !important;
        }

        .xl\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }

        .lg\:grid-cols-6 {
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        }
    }
</style>
@endpush
