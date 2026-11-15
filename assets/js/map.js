/* ============================================================
   TernakTrack — Live map (Leaflet)
   ============================================================ */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMeters(value) {
        if (value === null || value === undefined) return '-';
        return value < 1000
            ? value.toFixed(1).replace('.', ',') + ' m'
            : (value / 1000).toFixed(2).replace('.', ',') + ' km';
    }

    function formatJam(value) {
        if (!value) return '-';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function statusMeta(animal) {
        if (animal.is_inside === null) {
            return { key: 'unknown', label: 'Belum ada data', badge: 'badge-muted', icon: 'fa-circle-minus' };
        }
        if (animal.is_inside) {
            return { key: 'inside', label: 'Dalam radius', badge: 'badge-success', icon: 'fa-circle-check' };
        }
        return { key: 'outside', label: 'Di luar radius', badge: 'badge-danger', icon: 'fa-triangle-exclamation' };
    }

    function buildPopup(animal) {
        const status = statusMeta(animal);
        return `
            <div class="popup-title">${escapeHtml(animal.nama)}</div>
            <div class="popup-sub">${escapeHtml(animal.kode)} &middot; ${escapeHtml(animal.jenis || '-')} &middot; ${escapeHtml(animal.kandang_nama)}</div>
            <div class="mb-1"><span class="badge ${status.badge}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div>
            <div class="popup-row"><span>Jarak dari pusat</span><span>${formatMeters(animal.distance_m)}</span></div>
            <div class="popup-row"><span>Radius aman</span><span>${animal.radius} m</span></div>
            <div class="popup-row"><span>Akurasi GPS</span><span>${animal.accuracy === null ? '-' : formatMeters(animal.accuracy)}</span></div>
            <div class="popup-row"><span>Update terakhir</span><span>${formatJam(animal.recorded_at)}</span></div>
        `;
    }

    class LiveMap {
        constructor(elementId, options = {}) {
            this.el = typeof elementId === 'string' ? document.getElementById(elementId) : elementId;
            if (!this.el) return;

            this.options = Object.assign({
                endpoint: (window.TERNAK?.baseUrl || '/') + 'api/positions.php',
                refreshMs: window.TERNAK?.pollInterval || 5000,
                zoom: 16,
                fit: true,
                autoStart: true,
                showHome: true,
                center: window.TERNAK?.mapCenter || [1.1186, 104.0486],
                onUpdate: null,
                onAlert: null,
                onError: null,
                onSelect: null
            }, options);

            this.map = L.map(this.el, {
                zoomControl: true,
                attributionControl: true
            }).setView(this.options.center, this.options.zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(this.map);

            this.circleLayer = L.layerGroup().addTo(this.map);
            this.markerLayer = L.layerGroup().addTo(this.map);

            this.circles = {};
            this.homes = {};
            this.markers = {};
            this.knownAlerts = new Set();
            this.firstLoad = true;
            this.data = null;
            this.timer = null;

            if (this.options.autoStart) this.start();
        }

        start() {
            this.load();
            if (this.options.refreshMs > 0) {
                this.timer = setInterval(() => this.load(), this.options.refreshMs);
            }
        }

        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        }

        refresh() {
            return this.load();
        }

        async load() {
            try {
                const response = await fetch(this.options.endpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                });

                const data = await response.json().catch(() => null);

                if (!response.ok || !data || data.ok === false) {
                    throw new Error(data?.message || 'Gagal memuat data posisi (HTTP ' + response.status + ')');
                }

                this.render(data);
                this.data = data;

                if (typeof this.options.onUpdate === 'function') {
                    this.options.onUpdate(data, this);
                }

                this.firstLoad = false;
            } catch (error) {
                console.warn('[LiveMap]', error);
                if (typeof this.options.onError === 'function') {
                    this.options.onError(error);
                }
            }
        }

        render(data) {
            // ----- Geofence -----
            const kandangIds = new Set();

            (data.kandangs || []).forEach((kandang) => {
                kandangIds.add(kandang.id);

                if (this.circles[kandang.id]) {
                    this.circles[kandang.id]
                        .setLatLng([kandang.lat, kandang.lng])
                        .setRadius(kandang.radius);
                } else {
                    this.circles[kandang.id] = L.circle([kandang.lat, kandang.lng], {
                        radius: kandang.radius,
                        color: '#16a34a',
                        weight: 2,
                        dashArray: '7 7',
                        fillColor: '#22c55e',
                        fillOpacity: 0.10
                    }).addTo(this.circleLayer);

                    this.circles[kandang.id].bindTooltip(
                        kandang.nama + ' · radius ' + kandang.radius + ' m',
                        { sticky: true, direction: 'top' }
                    );
                }

                if (this.options.showHome) {
                    if (this.homes[kandang.id]) {
                        this.homes[kandang.id].setLatLng([kandang.lat, kandang.lng]);
                    } else {
                        const homeIcon = L.divIcon({
                            className: '',
                            html: '<div class="map-pin home"><i class="fa-solid fa-house"></i></div>',
                            iconSize: [30, 30],
                            iconAnchor: [15, 15]
                        });

                        this.homes[kandang.id] = L.marker([kandang.lat, kandang.lng], { icon: homeIcon })
                            .addTo(this.markerLayer)
                            .bindPopup(
                                '<div class="popup-title">' + escapeHtml(kandang.nama) + '</div>' +
                                '<div class="popup-sub">' + escapeHtml(kandang.lokasi || 'Titik pusat kandang') + '</div>' +
                                '<div class="popup-row"><span>Radius aman</span><span>' + kandang.radius + ' m</span></div>' +
                                '<div class="popup-row"><span>Buffer GPS</span><span>&plusmn;' + kandang.buffer + ' m</span></div>' +
                                '<div class="popup-row"><span>Ternak terdaftar</span><span>' + kandang.jumlah + ' ekor</span></div>'
                            );
                    }
                }
            });

            Object.keys(this.circles).forEach((id) => {
                if (!kandangIds.has(Number(id))) {
                    this.circleLayer.removeLayer(this.circles[id]);
                    delete this.circles[id];
                }
            });

            Object.keys(this.homes).forEach((id) => {
                if (!kandangIds.has(Number(id))) {
                    this.markerLayer.removeLayer(this.homes[id]);
                    delete this.homes[id];
                }
            });

            // ----- Marker ternak -----
            const animalIds = new Set();

            (data.animals || []).forEach((animal) => {
                if (animal.lat === null || animal.lng === null) return;

                animalIds.add(animal.id);
                const status = statusMeta(animal);
                const pos = [animal.lat, animal.lng];

                const icon = L.divIcon({
                    className: '',
                    html: '<div class="map-pin ' + status.key + '">' + window.TernakIcons.ayam + '</div>',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });

                if (this.markers[animal.id]) {
                    this.markers[animal.id]
                        .setLatLng(pos)
                        .setIcon(icon)
                        .setPopupContent(buildPopup(animal));
                } else {
                    const marker = L.marker(pos, { icon })
                        .addTo(this.markerLayer)
                        .bindPopup(buildPopup(animal));

                    marker.on('click', () => {
                        if (typeof this.options.onSelect === 'function') {
                            this.options.onSelect(animal, marker);
                        }
                    });

                    this.markers[animal.id] = marker;
                }
            });

            Object.keys(this.markers).forEach((id) => {
                if (!animalIds.has(Number(id))) {
                    this.markerLayer.removeLayer(this.markers[id]);
                    delete this.markers[id];
                }
            });

            // ----- Notifikasi alert -----
            (data.alerts || []).forEach((alert) => {
                if (this.knownAlerts.has(alert.id)) return;

                this.knownAlerts.add(alert.id);

                if (!this.firstLoad && typeof this.options.onAlert === 'function') {
                    this.options.onAlert(alert);
                }
            });

            // ----- Fit bounds pertama kali -----
            if (this.firstLoad && this.options.fit) {
                const points = [];
                let bounds = null;

                (data.kandangs || []).forEach((kandang) => {
                    const latDelta = (Math.max(10, kandang.radius) / 111320) * 1.2;
                    const lngDelta = (Math.max(10, kandang.radius) / (111320 * Math.cos(kandang.lat * Math.PI / 180))) * 1.2;
                    const kandangBounds = L.latLngBounds(
                        [kandang.lat - latDelta, kandang.lng - lngDelta],
                        [kandang.lat + latDelta, kandang.lng + lngDelta]
                    );
                    bounds = bounds ? bounds.extend(kandangBounds) : kandangBounds;
                });

                (data.animals || []).forEach((animal) => {
                    if (animal.lat !== null && animal.lng !== null) {
                        points.push([animal.lat, animal.lng]);
                    }
                });

                points.forEach((point) => {
                    bounds = bounds ? bounds.extend(point) : L.latLngBounds(point, point);
                });

                if (bounds) {
                    this.map.fitBounds(bounds, { padding: [34, 34], maxZoom: 18 });
                }
            }
        }

        focusAnimal(animalId) {
            const marker = this.markers[animalId];
            if (!marker) return;
            this.map.flyTo(marker.getLatLng(), Math.max(this.map.getZoom(), 17), { duration: 0.6 });
            marker.openPopup();
        }

        fitAll() {
            if (!this.data) return;
            const points = [];
            (this.data.kandangs || []).forEach((k) => points.push([k.lat, k.lng]));
            (this.data.animals || []).forEach((a) => {
                if (a.lat !== null && a.lng !== null) points.push([a.lat, a.lng]);
            });
            if (points.length) {
                this.map.fitBounds(points, { padding: [40, 40], maxZoom: 18 });
            }
        }
    }

    const ayamSvg =
        '<svg class="ikon-ayam" viewBox="0 0 64 64" aria-hidden="true" focusable="false">' +
            '<g fill="currentColor">' +
                '<path d="M17.5 42.5C7 40.5 2.5 30 6 19.5c1.2 7.5 6.4 11.8 13.6 13.8z"/>' +
                '<ellipse cx="31" cy="40" rx="20" ry="15"/>' +
                '<circle cx="46" cy="24" r="11"/>' +
                '<path d="M37.2 17.3c-1-6.2 4.2-9 6.1-4.7 1-5.2 6.2-5.3 7.2-1.1 4.1-1.9 7.1 2.4 4.1 6.4z"/>' +
                '<polygon points="55.8,20.8 63.6,24.8 55.8,28.9"/>' +
                '<ellipse cx="52.2" cy="33.4" rx="3.4" ry="5.2"/>' +
            '</g>' +
            '<g fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">' +
                '<path d="M25.5 53.4v8.4M36.5 53.4v8.4"/>' +
                '<path d="M20.5 61.8h10M31.5 61.8h10"/>' +
            '</g>' +
        '</svg>';

    window.TernakIcons = { ayam: ayamSvg };

    window.LiveMap = LiveMap;
    window.TernakMapUtils = {
        escapeHtml,
        formatMeters,
        formatJam,
        statusMeta
    };
})();
