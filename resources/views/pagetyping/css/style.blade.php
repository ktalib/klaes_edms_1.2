<style>
  /* Base styles */
  :root {
    --primary: #3b82f6;
    --primary-foreground: #ffffff;
    --muted: #f3f4f6;
    --muted-foreground: #6b7280;
    --border: #e5e7eb;
    --ring: #3b82f6;
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #0f172a;
    background-color: #f8fafc;
  }

  /* Card styles */
  .card {
    background-color: white;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  /* Button styles */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    line-height: 1.25rem;
    padding: 0.5rem 1rem;
    transition: all 0.2s;
    cursor: pointer;
  }

  .btn-primary {
    background-color: var(--primary);
    color: var(--primary-foreground);
  }

  .btn-primary:hover {
    background-color: #2563eb;
  }

  .btn-outline {
    background-color: transparent;
    border: 1px solid var(--border);
  }

  .btn-outline:hover {
    background-color: var(--muted);
  }

  .btn-ghost {
    background-color: transparent;
  }

  .btn-ghost:hover {
    background-color: var(--muted);
  }

  .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
  }

  .btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
  }

  .btn-icon {
    padding: 0.25rem;
  }

  .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  /* Badge styles */
  .badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
  }

  .badge-outline {
    background-color: transparent;
    border: 1px solid var(--border);
  }

  .badge-secondary {
    background-color: #f3f4f6;
    color: #1f2937;
  }

  /* Input styles */
  .input {
    display: block;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: white;
  }

  .input:focus {
    outline: none;
    border-color: var(--ring);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
  }

  /* Prevent page jumping when typing in input fields */
  #page-type-others, #page-subtype-others {
    scroll-margin-top: 100px;
    transition: none !important;
    will-change: auto;
  }

  #page-type-others:focus, #page-subtype-others:focus {
    outline: none;
    border-color: var(--ring);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    scroll-behavior: auto !important;
    transform: none !important;
  }

  /* Prevent any transitions or animations that might cause jumping */
  #page-type-others-container, #page-subtype-others-container {
    transition: none !important;
    transform: none !important;
  }

  /* Ensure stable layout for the typing interface */
  .typing-interface {
    position: relative;
    overflow: visible;
  }

  /* Prevent any auto-scroll behavior */
  body {
    scroll-behavior: auto !important;
  }

  /* Textarea styles */
  .textarea {
    display: block;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: white;
    resize: vertical;
  }

  .textarea:focus {
    outline: none;
    border-color: var(--ring);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
  }

  /* Progress bar */
  .progress {
    position: relative;
    width: 100%;
    height: 0.5rem;
    overflow: hidden;
    background-color: var(--muted);
    border-radius: 9999px;
  }

  .progress-bar {
    position: absolute;
    height: 100%;
    background-color: var(--primary);
    transition: width 0.3s ease;
  }

  /* Tab styles */
  .tabs {
    display: flex;
    flex-direction: column;
    width: 100%;
  }

  .tabs-list {
    display: flex;
    border-bottom: 1px solid var(--border);
  }

  .tab {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    cursor: pointer;
  }

  .tab[aria-selected="true"] {
    border-bottom-color: var(--primary);
    color: var(--primary);
  }

  .tab[aria-disabled="true"] {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .tab-content {
    display: none;
    padding-top: 1.5rem;
  }

  .tab-content[aria-hidden="false"] {
    display: block;
  }

  /* Custom animations */
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  .animate-spin {
    animation: spin 1s linear infinite;
  }

  /* Hide scrollbar for Chrome, Safari and Opera */
  .no-scrollbar::-webkit-scrollbar {
    display: none;
  }

  /* Hide scrollbar for IE, Edge and Firefox */
  .no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
  }

  /* ---- Edit Tools: Toolbar & Crop ---- */
  .toolbar-separator {
    display: inline-block;
    width: 1px;
    height: 16px;
    background: #d1d5db;
    margin: 0 2px;
    vertical-align: middle;
  }

  .btn-active {
    background-color: #dbeafe !important;
    color: #2563eb !important;
    box-shadow: inset 0 0 0 1.5px #3b82f6;
  }

  .btn-danger-icon {
    color: #dc2626;
  }
  .btn-danger-icon:hover {
    background-color: #fee2e2 !important;
    color: #b91c1c !important;
  }

  /* Crop overlay */
  .crop-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    cursor: crosshair;
    z-index: 20;
    display: none;
  }

  .crop-selection {
    position: absolute;
    border: 2px dashed #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    display: none;
    pointer-events: none;
  }

  .crop-actions {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: none;
    gap: 8px;
    z-index: 25;
    background: white;
    padding: 6px 10px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }

  .preview-image-container {
    position: relative;
  }
</style>