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

  .badge-default {
    background-color: var(--primary);
    color: var(--primary-foreground);
  }

  .badge-destructive {
    background-color: #ef4444;
    color: white;
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

  /* Select styles */
  .select {
    display: block;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: white;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
  }

  .select:focus {
    outline: none;
    border-color: var(--ring);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
  }

  /* Dialog styles */
  .dialog-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
  }

  .dialog-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    margin: auto;
    position: relative;
  }

  /* Responsive modal adjustments */
  @media (max-width: 768px) {
    .dialog-backdrop {
      padding: 0.5rem;
    }
    
    .dialog-content {
      max-width: 100%;
      max-height: 95vh;
      margin: 0;
    }
  }

  @media (max-width: 640px) {
    .dialog-backdrop {
      padding: 0.25rem;
      align-items: flex-start;
      padding-top: 2rem;
    }
    
    .dialog-content {
      max-height: calc(100vh - 2rem);
      width: 100%;
    }
  }

  /* Mark style for search highlighting */
  mark {
    background-color: #fef08a;
    padding: 0.1em 0.2em;
    border-radius: 0.2em;
  }

  /* Custom animations */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
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
</style>