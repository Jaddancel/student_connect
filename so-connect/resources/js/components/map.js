import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const TARLAC_GEOJSON_URL = 'https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2023/geojson/provdists/hires/municities-provdist-306900000.0.1.json';

const CUSTOMERS_BY_MUNICIPALITY_PSGC = {
    306916000: 1542, // City of Tarlac
    306904000: 1220, // Capas
    306905000: 1008, // Concepcion
    306902000: 790, // Bamban
};

const getMunicipalityName = (properties = {}) => {
    return (
        properties.municity_name ||
        properties.MUNICITY ||
        properties.NAME_2 ||
        properties.ADM3_EN ||
        properties.NAME ||
        'Municipality'
    );
};

const getProvinceName = (properties = {}) => {
    return (
        properties.provdist_name ||
        properties.PROVDIST ||
        properties.NAME_1 ||
        properties.ADM2_EN ||
        ''
    );
};

const getCustomerCount = (properties = {}) => {
    const psgc = Number(properties.adm3_psgc);
    return CUSTOMERS_BY_MUNICIPALITY_PSGC[psgc] || 0;
};

const getChoroplethColor = (value) => {
    if (value > 1400) return '#1D4ED8';
    if (value > 1100) return '#2563EB';
    if (value > 900) return '#3B82F6';
    if (value > 700) return '#60A5FA';
    if (value > 0) return '#93C5FD';
    return '#DBEAFE';
};

const loadMunicipalityGeoJson = async () => {
    const response = await fetch(TARLAC_GEOJSON_URL);

    if (!response.ok) {
        throw new Error('Failed to fetch Tarlac municipality boundaries.');
    }

    return response.json();
};

export const initMap = () => {
    const mapElement = document.querySelector('#mapOne');

    if (mapElement) {
        const mapOne = L.map(mapElement, {
            zoomControl: true,
            minZoom: 8,
            maxZoom: 13,
        }).setView([15.5, 120.55], 9);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(mapOne);

        loadMunicipalityGeoJson()
            .then((municipalityGeoJson) => {
                const municipalitiesLayer = L.geoJSON(municipalityGeoJson, {
                    style: (feature) => {
                        const customerCount = getCustomerCount(feature.properties);

                        return {
                            color: '#1E3A8A',
                            weight: 1,
                            fillColor: getChoroplethColor(customerCount),
                            fillOpacity: 0.75,
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        const municipalityName = getMunicipalityName(feature.properties);
                        const provinceName = getProvinceName(feature.properties);
                        const customerCount = getCustomerCount(feature.properties);
                        const placeLabel = provinceName ? `${municipalityName}, ${provinceName}` : municipalityName;
                        const customerLabel = customerCount > 0
                            ? `${customerCount.toLocaleString()} customers`
                            : 'No sample customers';

                        layer.bindTooltip(`${placeLabel}<br>${customerLabel}`, { sticky: true });
                    },
                }).addTo(mapOne);

                if (municipalitiesLayer.getBounds().isValid()) {
                    mapOne.fitBounds(municipalitiesLayer.getBounds(), {
                        padding: [10, 10],
                    });
                }

                const legend = L.control({ position: 'bottomright' });
                legend.onAdd = () => {
                    const div = L.DomUtil.create('div', 'info legend');
                    div.style.background = 'rgba(255,255,255,0.95)';
                    div.style.padding = '8px 10px';
                    div.style.borderRadius = '8px';
                    div.style.boxShadow = '0 2px 8px rgba(15, 23, 42, 0.15)';
                    div.style.fontSize = '12px';
                    div.style.lineHeight = '1.4';

                    const values = Object.values(CUSTOMERS_BY_MUNICIPALITY_PSGC);
                    const minValue = Math.min(...values);
                    const maxValue = Math.max(...values);

                    div.innerHTML = `
                        <strong>Customers</strong>
                        <div style="margin-top:6px; width:160px; height:10px; border-radius:999px; background:linear-gradient(90deg, #93C5FD 0%, #60A5FA 35%, #3B82F6 60%, #2563EB 80%, #1D4ED8 100%);"></div>
                        <div style="display:flex; justify-content:space-between; margin-top:4px; color:#334155;">
                            <span>${minValue.toLocaleString()}</span>
                            <span>${maxValue.toLocaleString()}</span>
                        </div>
                    `;

                    return div;
                };
                legend.addTo(mapOne);
            })
            .catch(() => {
                L.popup()
                    .setLatLng([15.5, 120.55])
                    .setContent('Unable to load municipality boundary data right now.')
                    .openOn(mapOne);
            });
    }
};

export default initMap;
