document.addEventListener('DOMContentLoaded', function () {

    if (!window.SM_POI_DATA || !document.getElementById('map')) {
        return;
    }

    const INVEST_CENTER = SM_POI_DATA.center;
    const POIS = SM_POI_DATA.pois;

    const SVG = (name) => `${SM_INV.assetsSvgBase}${name}`;

    const CATEGORY_META = {
        komunikacja: { color: '#AF423E', svg: 'bus-simple.svg' },
        restauracje: { color: '#AF423E', svg: 'fork-knife.svg' },
        zdrowie: { color: '#AF423E', svg: 'heart-pulse.svg' },
        oswiata: { color: '#AF423E', svg: 'school.svg' },
        sklepy: { color: '#AF423E', svg: 'shop.svg' },
        sport: { color: '#AF423E', svg: 'sport.svg' }
    };



    const map = L.map('map').setView(INVEST_CENTER, 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    const clusterGroup = L.markerClusterGroup({
        maxClusterRadius: 60,
        showCoverageOnHover: false,
        iconCreateFunction: function (cluster) {
            const count = cluster.getChildCount();
            return L.divIcon({
                html: `<div style="background:#2f2d7b; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:14px; border:2px solid white;">${count}</div>`,
                className: 'custom-cluster-icon',
                iconSize: [36, 36]
            });
        }
    });

    const allMarkers = [];

    POIS.forEach(poi => {

        if (!CATEGORY_META[poi.category]) return;

        const meta = CATEGORY_META[poi.category];
        const lat = parseFloat(poi.lat);
        const lng = parseFloat(poi.lng);
        const name = poi.name || 'POI';

        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: `
                <div style="
                    background:${meta.color};
                    width:32px;
                    height:32px;
                    border-radius:50%;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    border:2px solid white;
                    box-shadow:0 2px 6px rgba(0,0,0,0.2);
                ">
                    <img src="${SVG(meta.svg)}" 
                         alt="" 
                         style="width:16px;height:16px;display:block;">
                </div>
            `,
                className: 'poi-marker',
                iconSize: [24, 24]
            })
        });

        marker.category = poi.category;

        marker.bindPopup(`
        <div style="font-family:'Albert Sans',sans-serif; min-width:160px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                <div style="
                    background:${meta.color};
                    width:28px;
                    height:28px;
                    border-radius:50%;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                ">
                    <img src="${SVG(meta.svg)}"
                         alt=""
                         style="width:14px;height:14px;display:block;">
                </div>
                <span style="font-weight:700; color:${meta.color};">
                    ${name}
                </span>
            </div>
        </div>
    `, { maxWidth: 240 });

        clusterGroup.addLayer(marker);
        allMarkers.push(marker);
    });


    map.addLayer(clusterGroup);

    // Marker inwestycji
    L.marker(INVEST_CENTER, {
        icon: L.divIcon({
            html: `<div style="background:#2f2d7b; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:18px; border:3px solid white;">
                    <i class="fas fa-building"></i>
                   </div>`,
            className: 'investment-marker',
            iconSize: [40, 40]
        })
    }).addTo(map);

    // function updateStats() {
    //     const total = allMarkers.length;
    //     const visible = clusterGroup.getLayers().length;
    //     document.getElementById('stats').innerHTML =
    //         `📍 ${visible} / ${total} POI`;
    // }

    // updateStats();

    document.querySelectorAll('.filter-item input').forEach(cb => {

        cb.addEventListener('change', function () {

            clusterGroup.clearLayers();

            allMarkers.forEach(marker => {
                if (document.getElementById(marker.category)?.checked) {
                    clusterGroup.addLayer(marker);
                }
            });

            // updateStats();
        });
    });

    const allCheckbox = document.getElementById('wszystkie');
    const categoryCheckboxes = document.querySelectorAll(
        '.filter-item input:not(#wszystkie)'
    );

    function refreshMarkers() {
        clusterGroup.clearLayers();

        allMarkers.forEach(marker => {
            const cb = document.getElementById(marker.category);
            if (cb && cb.checked) {
                clusterGroup.addLayer(marker);
            }
        });

        // updateStats();
    }

    /*
    |--------------------------------------------------------------------------
    | Klik "Wszystkie"
    |--------------------------------------------------------------------------
    */
    allCheckbox.addEventListener('change', function () {

        categoryCheckboxes.forEach(cb => {
            cb.checked = allCheckbox.checked;
        });

        refreshMarkers();
    });

    /*
    |--------------------------------------------------------------------------
    | Klik pojedynczej kategorii
    |--------------------------------------------------------------------------
    */
    categoryCheckboxes.forEach(cb => {

        cb.addEventListener('change', function () {

            const allChecked = [...categoryCheckboxes].every(c => c.checked);
            allCheckbox.checked = allChecked;

            refreshMarkers();
        });
    });
});
