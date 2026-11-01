/**
 * Raptor Analytics & Lead Ingestion First-Party Tracking Snippet (raptor.js)
 * -------------------------------------------------------------------------
 * - Generates & persists anonymous visitor cookie (_rpt_uid)
 * - Captures pageviews, referrers, and UTM marketing parameters
 * - Automatically stitches anonymous web sessions to forms upon submission
 * - Renders embeddable forms dynamically if targeted
 */
(function(window, document) {
    'use strict';

    var COOKIE_NAME = '_rpt_uid';
    var COOKIE_DAYS = 365;

    // Helper: Generate UUID v4
    function generateUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Helper: Cookie Get/Set
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    function setCookie(name, val, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(val) + expires + '; path=/; SameSite=Lax';
    }

    // Initialize or read visitor UUID
    var visitorUid = getCookie(COOKIE_NAME);
    if (!visitorUid) {
        visitorUid = generateUuid();
        setCookie(COOKIE_NAME, visitorUid, COOKIE_DAYS);
    }

    // Parse UTMs
    function getUrlParams() {
        var params = {};
        var search = window.location.search.substring(1);
        if (!search) return params;
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var part = pairs[i].split('=');
            if (part[0]) {
                params[decodeURIComponent(part[0])] = decodeURIComponent(part[1] || '');
            }
        }
        return params;
    }

    var utmParams = getUrlParams();

    // Determine Collector Base URL
    var scripts = document.getElementsByTagName('script');
    var currentScript = document.currentScript || scripts[scripts.length - 1];
    var scriptSrc = currentScript ? currentScript.src : '';
    var baseUrl = scriptSrc ? scriptSrc.replace(/\/raptor\.js.*$/, '') : '';

    var collectorEndpoint = (baseUrl || '') + '/index.php?route=api_tracking/collect';

    // Send tracking beacon
    function sendPayload(endpoint, payload) {
        var json = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            var blob = new Blob([json], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(json);
        }
    }

    // Record Pageview
    var pageviewPayload = {
        visitor_cookie: visitorUid,
        page_url: window.location.href,
        page_title: document.title,
        referrer: document.referrer || '',
        screen_res: window.screen.width + 'x' + window.screen.height,
        utm_source: utmParams.utm_source || '',
        utm_medium: utmParams.utm_medium || '',
        utm_campaign: utmParams.utm_campaign || '',
        utm_content: utmParams.utm_content || '',
        utm_term: utmParams.utm_term || ''
    };

    sendPayload(collectorEndpoint, pageviewPayload);

    // Auto-intercept standard web forms on page to stitch anonymous cookie to lead profile
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.getAttribute('data-raptor-ignore') !== null) {
            return;
        }

        var elements = form.elements;
        var email = '';
        var phone = '';
        var name = '';

        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            var type = (el.type || '').toLowerCase();
            var elName = (el.name || '').toLowerCase();
            var val = (el.value || '').trim();

            if (!val) continue;

            if (type === 'email' || elName.indexOf('email') !== -1) {
                email = val;
            } else if (type === 'tel' || elName.indexOf('phone') !== -1 || elName.indexOf('mobile') !== -1) {
                phone = val;
            } else if (elName === 'name' || elName === 'first_name') {
                name = val;
            }
        }

        // If identifiable info captured, report stitching event
        if (email || phone) {
            sendPayload(collectorEndpoint, {
                event_type: 'identify',
                visitor_cookie: visitorUid,
                email: email,
                phone: phone,
                name: name,
                page_url: window.location.href,
                utm_source: utmParams.utm_source || '',
                utm_campaign: utmParams.utm_campaign || ''
            });
        }
    }, true);

    // Dynamic Form Embed Renderer (if data-form-uuid specified on script tag)
    if (currentScript) {
        var targetFormUuid = currentScript.getAttribute('data-form-uuid');
        if (targetFormUuid) {
            var container = document.getElementById('raptor-form-' + targetFormUuid);
            if (container) {
                var iframe = document.createElement('iframe');
                iframe.src = (baseUrl || '') + '/index.php?route=forms/render&uuid=' + encodeURIComponent(targetFormUuid);
                iframe.style.width = '100%';
                iframe.style.height = '480px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                iframe.title = 'Raptor Lead Form';
                container.appendChild(iframe);
            }
        }
    }

    // Expose global object
    window.Raptor = {
        getVisitorId: function() { return visitorUid; },
        track: function(eventName, payload) {
            var p = payload || {};
            p.event_type = eventName;
            p.visitor_cookie = visitorUid;
            p.page_url = window.location.href;
            sendPayload(collectorEndpoint, p);
        }
    };
})(window, document);

