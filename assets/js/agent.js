/* ============================================================
   CUSTOM CONFIRMATION MODAL
   ============================================================ */
.je-confirm-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    animation: jeConfirmFadeIn 0.2s ease;
}

.je-confirm-modal.is-visible {
    display: flex;
}

.je-confirm-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.je-confirm-content {
    position: relative;
    background: #ffffff;
    border-radius: 16px;
    padding: 40px 48px 32px;
    max-width: 440px;
    width: calc(100% - 32px);
    text-align: center;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.25);
    animation: jeConfirmSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 1;
}

.je-confirm-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
}

.je-confirm-icon.warning {
    background: #FFF3E0;
    color: #E65100;
}

.je-confirm-icon.danger {
    background: #FFEBEE;
    color: #C62828;
}

.je-confirm-icon.success {
    background: #E8F5E9;
    color: #2E7D32;
}

.je-confirm-icon.info {
    background: #E3F2FD;
    color: #0D47A1;
}

.je-confirm-title {
    font-family: 'Prata', serif;
    font-size: 20px;
    color: #0A0A0A;
    margin-bottom: 8px;
}

.je-confirm-message {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 24px;
}

.je-confirm-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.je-confirm-btn {
    padding: 12px 28px;
    border: none;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 100px;
}

.je-confirm-btn-cancel {
    background: #f5f5f5;
    color: #555;
}

.je-confirm-btn-cancel:hover {
    background: #e8e8e8;
}

.je-confirm-btn-confirm {
    background: #C6A43F;
    color: #0A0A0A;
}

.je-confirm-btn-confirm:hover {
    background: #A8882E;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(198, 164, 63, 0.3);
}

.je-confirm-btn-confirm.danger {
    background: #C62828;
    color: #ffffff;
}

.je-confirm-btn-confirm.danger:hover {
    background: #B71C1C;
    box-shadow: 0 4px 16px rgba(198, 40, 40, 0.3);
}

@keyframes jeConfirmFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes jeConfirmSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive */
@media (max-width: 480px) {
    .je-confirm-content {
        padding: 28px 20px 24px;
    }
    
    .je-confirm-icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
    }
    
    .je-confirm-title {
        font-size: 18px;
    }
    
    .je-confirm-message {
        font-size: 13px;
    }
    
    .je-confirm-btn {
        padding: 10px 20px;
        font-size: 13px;
        min-width: 80px;
    }
}
