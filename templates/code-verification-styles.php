<style>
    /* Message Styles */
    .evf-message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-weight: 500;
        display: none;
    }

    .evf-message.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    .evf-message.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .evf-message.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .evf-message.warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Page Layout */
    .evf-code-verification-page {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea, #764ba2);
        min-height: 100vh;
    }

    .evf-code-verification-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .evf-code-verification-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 2.5rem;
        width: 100%;
        max-width: 450px;
        position: relative;
        overflow: hidden;
    }

    .evf-code-verification-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    /* Progress Bar */
    .evf-progress-bar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding: 0 1rem;
    }

    .evf-progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
    }

    .evf-progress-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 20px;
        left: 60%;
        width: 100%;
        height: 2px;
        background: #e5e7eb;
        z-index: 1;
    }

    .evf-progress-step.completed:not(:last-child)::after {
        background: #10b981;
    }

    .evf-progress-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        position: relative;
        z-index: 2;
        background: #f3f4f6;
        color: #6b7280;
        border: 2px solid #e5e7eb;
    }

    .evf-progress-step.completed .evf-progress-circle {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .evf-progress-step.active .evf-progress-circle {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .evf-progress-label {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #6b7280;
        text-align: center;
    }

    .evf-progress-step.completed .evf-progress-label,
    .evf-progress-step.active .evf-progress-label {
        color: #374151;
        font-weight: 500;
    }

    /* Form Header */
    .evf-form-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .evf-code-icon {
        font-size: 3rem;
        text-align: center;
        margin-bottom: 1rem;
    }

    .evf-form-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 1rem 0;
    }

    .evf-form-subtitle {
        color: #6b7280;
        margin: 0;
        line-height: 1.5;
    }

    /* Form Styles */
    .evf-form-group {
        margin-bottom: 1.5rem;
    }

    .evf-label {
        display: block;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .evf-label .required {
        color: #ef4444;
        margin-left: 0.25rem;
    }

    .evf-code-input-wrapper {
        position: relative;
    }

    .evf-code-input {
        width: 100%;
        padding: 1rem;
        font-size: 1.5rem;
        text-align: center;
        letter-spacing: 0.5rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
        transition: all 0.2s ease;
        font-family: 'Monaco', 'Consolas', monospace;
        box-sizing: border-box;
    }

    .evf-code-input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .evf-code-input.success {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .evf-code-input.error {
        border-color: #ef4444;
        background: #fef2f2;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .evf-code-input-help {
        text-align: center;
        font-size: 0.875rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    /* Button Styles */
    .evf-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1rem;
        line-height: 1.5;
    }

    .evf-btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 4px 14px 0 rgba(102, 126, 234, 0.4);
    }

    .evf-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px 0 rgba(102, 126, 234, 0.6);
    }

    .evf-btn-secondary {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .evf-btn-secondary:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .evf-btn-full {
        width: 100%;
    }

    .evf-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Spinner Animations */
    .evf-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }

    .evf-spinner-small {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #e5e7eb;
        border-top: 2px solid #6b7280;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Resend Section */
    .evf-resend-section {
        text-align: center;
        margin: 2rem 0;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .evf-resend-text {
        margin: 0 0 1rem 0;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .evf-resend-btn {
        min-width: 200px;
        position: relative;
    }

    .evf-resend-countdown {
        color: #f59e0b;
        font-weight: 600;
    }

    .evf-resend-loading {
        color: #6b7280;
    }

    /* Help Section */
    .evf-help-section {
        margin: 2rem 0;
    }

    .evf-help-details {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 8px;
        padding: 1rem;
    }

    .evf-help-details summary {
        font-weight: 600;
        color: #0369a1;
        cursor: pointer;
        outline: none;
    }

    .evf-help-details[open] summary {
        margin-bottom: 1rem;
    }

    .evf-help-content ul {
        margin: 0;
        padding-left: 1.25rem;
        color: #0c4a6e;
    }

    .evf-help-content li {
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    /* Back Section */
    .evf-back-section {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .evf-back-link {
        color: #6b7280;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease;
    }

    .evf-back-link:hover {
        color: #374151;
        text-decoration: underline;
    }

    /* Animations */
    .evf-fade-in {
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive Design */
    @media (max-width: 640px) {
        .evf-code-verification-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }

        .evf-code-input {
            font-size: 1.25rem;
            letter-spacing: 0.25rem;
        }

        .evf-resend-section {
            padding: 1rem;
        }

        .evf-progress-bar {
            margin-bottom: 1.5rem;
        }

        .evf-progress-circle {
            width: 35px;
            height: 35px;
            font-size: 0.8rem;
        }
    }

    @media (max-width: 480px) {
        .evf-code-verification-wrapper {
            padding: 1rem 0.5rem;
        }

        .evf-code-verification-card {
            padding: 1.5rem 1rem;
        }

        .evf-code-icon {
            font-size: 2.5rem;
        }

        .evf-form-title {
            font-size: 1.25rem;
        }
    }
</style>