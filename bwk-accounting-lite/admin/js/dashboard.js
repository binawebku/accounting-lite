(function(){
    'use strict';

    function onReady(callback) {
        if ( document.readyState !== 'loading' ) {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
    }

    function setState(text, state) {
        var el = document.getElementById('bwk-dashboard-chart-state');
        if ( ! el ) {
            return;
        }
        el.textContent = text || '';
        el.dataset.state = state || '';
        el.style.display = text ? 'block' : 'none';
    }

    function fetchChart() {
        var settings = window.bwkDashboardData || {};
        var ajaxUrl = settings.ajaxUrl;
        var nonce = settings.chartNonce;
        var i18n = settings.i18n || {};
        var canvas = document.getElementById('bwk-dashboard-chart');
        if ( ! ajaxUrl || ! nonce || ! canvas ) {
            return;
        }

        setState(i18n.loading || 'Loading…', 'loading');

        var params = new URLSearchParams();
        params.append('action', 'bwk_dashboard_chart');
        params.append('nonce', nonce);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString()
        })
        .then(function(response){ return response.json(); })
        .then(function(payload){
            if ( ! payload || ! payload.success || ! payload.data ) {
                throw new Error('Invalid payload');
            }
            var rendered = renderChart(canvas, payload.data);
            updateLegend(payload.data);
            if ( rendered ) {
                setState('', '');
            } else {
                setState(i18n.empty || 'Not enough data yet.', 'empty');
            }
        })
        .catch(function(){
            setState(i18n.error || 'Unable to load chart data.', 'error');
        });
    }

    function renderChart(canvas, payload) {
        var wrapper = canvas && canvas.parentNode ? canvas.parentNode : null;
        var values = payload && payload.values ? payload.values : [];
        var labels = payload && payload.labels ? payload.labels : [];
        if ( ! canvas ) {
            return false;
        }
        if ( ! values.length || ! labels.length ) {
            if ( wrapper ) {
                wrapper.classList.add('is-empty');
            }
            return false;
        }
        if ( wrapper ) {
            wrapper.classList.remove('is-empty');
        }

        var dpr = window.devicePixelRatio || 1;
        var width = canvas.clientWidth * dpr;
        var height = canvas.clientHeight * dpr;
        if ( ! width || ! height ) {
            width = 640 * dpr;
            height = 320 * dpr;
        }
        canvas.width = width;
        canvas.height = height;

        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, width, height);

        var padding = 32 * dpr;
        var plotWidth = width - padding * 2;
        var plotHeight = height - padding * 2;
        if ( plotWidth <= 0 || plotHeight <= 0 ) {
            return;
        }

        var maxValue = values.reduce(function(max, value){
            var numeric = parseFloat(value);
            if ( isNaN(numeric) ) {
                numeric = 0;
            }
            return numeric > max ? numeric : max;
        }, 0);
        if ( maxValue <= 0 ) {
            maxValue = 1;
        }

        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        // Axes
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1 * dpr;
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, padding + plotHeight);
        ctx.lineTo(padding + plotWidth, padding + plotHeight);
        ctx.stroke();

        // Grid lines
        ctx.strokeStyle = '#f3f4f6';
        ctx.lineWidth = 1 * dpr;
        var gridSteps = 3;
        for ( var i = 1; i <= gridSteps; i++ ) {
            var y = padding + plotHeight - ( plotHeight * i / ( gridSteps + 1 ) );
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(padding + plotWidth, y);
            ctx.stroke();
        }

        // Area fill
        var gradient = ctx.createLinearGradient(0, padding, 0, padding + plotHeight);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.2)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');
        ctx.fillStyle = gradient;
        ctx.beginPath();
        values.forEach(function(value, index){
            var numeric = parseFloat(value);
            if ( isNaN(numeric) ) {
                numeric = 0;
            }
            var x = padding;
            if ( values.length > 1 ) {
                x += plotWidth * ( index / ( values.length - 1 ) );
            }
            var y = padding + plotHeight - ( plotHeight * ( numeric / maxValue ) );
            if ( index === 0 ) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.lineTo(padding + plotWidth, padding + plotHeight);
        ctx.lineTo(padding, padding + plotHeight);
        ctx.closePath();
        ctx.fill();

        // Line stroke
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 2 * dpr;
        ctx.beginPath();
        values.forEach(function(value, index){
            var numeric = parseFloat(value);
            if ( isNaN(numeric) ) {
                numeric = 0;
            }
            var x = padding;
            if ( values.length > 1 ) {
                x += plotWidth * ( index / ( values.length - 1 ) );
            }
            var y = padding + plotHeight - ( plotHeight * ( numeric / maxValue ) );
            if ( index === 0 ) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        // Points
        ctx.fillStyle = '#1d4ed8';
        values.forEach(function(value, index){
            var numeric = parseFloat(value);
            if ( isNaN(numeric) ) {
                numeric = 0;
            }
            var x = padding;
            if ( values.length > 1 ) {
                x += plotWidth * ( index / ( values.length - 1 ) );
            }
            var y = padding + plotHeight - ( plotHeight * ( numeric / maxValue ) );
            ctx.beginPath();
            ctx.arc(x, y, 3 * dpr, 0, Math.PI * 2, true);
            ctx.fill();
        });

        return true;
    }

    function updateLegend(payload) {
        var list = document.getElementById('bwk-dashboard-chart-legend');
        var wrapper = list && list.parentNode ? list.parentNode : null;
        if ( ! list ) {
            return;
        }
        while ( list.firstChild ) {
            list.removeChild(list.firstChild);
        }

        var labels = payload && payload.labels ? payload.labels : [];
        var values = payload && payload.values ? payload.values : [];
        var currency = payload && payload.currency ? payload.currency : '';

        if ( ! labels.length || ! values.length ) {
            if ( wrapper ) {
                wrapper.classList.add('is-empty');
            }
            return;
        }

        if ( wrapper ) {
            wrapper.classList.remove('is-empty');
        }

        labels.forEach(function(label, index){
            var value = values[index];
            var item = document.createElement('li');
            item.className = 'bwk-dashboard-legend-item';

            var labelSpan = document.createElement('span');
            labelSpan.className = 'bwk-dashboard-legend-label';
            labelSpan.textContent = label;

            var valueSpan = document.createElement('span');
            valueSpan.className = 'bwk-dashboard-legend-value';
            var numeric = parseFloat(value);
            if ( isNaN(numeric) ) {
                valueSpan.textContent = value;
            } else {
                var formatted = numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                valueSpan.textContent = ( currency ? currency + ' ' : '' ) + formatted;
            }

            item.appendChild(labelSpan);
            item.appendChild(valueSpan);
            list.appendChild(item);
        });
    }

    onReady(fetchChart);
})();
