/**
 * Admin Conflict Logger - Error Logger Script
 * Captures JavaScript errors and sends them to WordPress
 */

(function() {
    'use strict';

    // Prevent multiple initializations
    if (window.ACL_INITIALIZED) {
        return;
    }
    window.ACL_INITIALIZED = true;

    // Check if config exists
    if (typeof aclConfig === 'undefined') {
        return;
    }

    // Debounce to prevent flooding
    const errorQueue = [];
    let isProcessing = false;
    const DEBOUNCE_MS = 1000;
    const MAX_QUEUE_SIZE = 10;

    /**
     * Send error to server
     */
    function sendError(errorData) {
        const formData = new FormData();
        formData.append('action', 'acl_log_error');
        formData.append('nonce', aclConfig.nonce);
        formData.append('message', errorData.message || '');
        formData.append('source', errorData.source || '');
        formData.append('line', errorData.line || 0);
        formData.append('column', errorData.column || 0);
        formData.append('stack', errorData.stack || '');
        formData.append('pageUrl', window.location.href);
        formData.append('pageHook', aclConfig.currentPage || '');
        formData.append('isAdmin', aclConfig.isAdmin ? '1' : '0');

        fetch(aclConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).catch(function(err) {
            // Silently fail - we don't want to cause more errors
            console.debug('ACL: Failed to log error', err);
        });
    }

    /**
     * Process error queue
     */
    function processQueue() {
        if (isProcessing || errorQueue.length === 0) {
            return;
        }

        isProcessing = true;
        const errorData = errorQueue.shift();
        sendError(errorData);

        setTimeout(function() {
            isProcessing = false;
            processQueue();
        }, DEBOUNCE_MS);
    }

    /**
     * Add error to queue
     */
    function queueError(errorData) {
        // Deduplicate by message + source
        const key = errorData.message + errorData.source;
        const exists = errorQueue.some(function(e) {
            return (e.message + e.source) === key;
        });

        if (!exists && errorQueue.length < MAX_QUEUE_SIZE) {
            errorQueue.push(errorData);
            processQueue();
        }
    }

    /**
     * Check if error should be ignored
     */
    function shouldIgnore(message, source) {
        // Ignore our own errors
        if (source && source.indexOf('error-logger.js') !== -1) {
            return true;
        }

        // Ignore common non-actionable errors
        const ignorePatterns = [
            /ResizeObserver loop/i,
            /Script error\.?$/i,
            /Failed to fetch/i,
            /Load failed/i,
            /NetworkError/i,
            /AbortError/i,
            /ChunkLoadError/i,
        ];

        return ignorePatterns.some(function(pattern) {
            return pattern.test(message);
        });
    }

    /**
     * Global error handler
     */
    window.addEventListener('error', function(event) {
        if (shouldIgnore(event.message, event.filename)) {
            return;
        }

        queueError({
            message: event.message,
            source: event.filename,
            line: event.lineno,
            column: event.colno,
            stack: event.error ? event.error.stack : ''
        });
    });

    /**
     * Unhandled promise rejection handler
     */
    window.addEventListener('unhandledrejection', function(event) {
        let message = 'Unhandled Promise Rejection';
        let stack = '';

        if (event.reason) {
            if (typeof event.reason === 'string') {
                message = event.reason;
            } else if (event.reason.message) {
                message = event.reason.message;
                stack = event.reason.stack || '';
            }
        }

        if (shouldIgnore(message, '')) {
            return;
        }

        queueError({
            message: message,
            source: 'Promise',
            line: 0,
            column: 0,
            stack: stack
        });
    });

    // Log initialization (debug only)
    console.debug('ACL: Error logger initialized', {
        page: aclConfig.currentPage,
        isAdmin: aclConfig.isAdmin,
        plugins: aclConfig.activePlugins.length
    });

})();
