import L from 'leaflet';
import * as turf from '@turf/turf';
import 'leaflet/dist/leaflet.css';

const BARANGAY_MAP_RESOLUTION = 'hires';
const BARANGAY_GEOJSON_BASE_URL = `https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2019/geojson/barangays/${BARANGAY_MAP_RESOLUTION}`;
const MUNICTIES_MAP_RESOLUTION = 'medres';
const PROVINCES_MAP_RESOLUTION = 'medres';
const COUNTRY_WORLD_GEOJSON_URL = 'https://raw.githubusercontent.com/johan/world.geo.json/master/countries.geo.json';
const MUNICTIES_GEOJSON_BASE_URL = `https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2019/geojson/municties/${MUNICTIES_MAP_RESOLUTION}`;
const PROVINCES_GEOJSON_BASE_URL = `https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2019/geojson/provinces/${PROVINCES_MAP_RESOLUTION}`;
const BARANGAY_FILE_SUFFIX_BY_RESOLUTION = {
    hires: '0.1',
    medres: '0.01',
    lowres: '0.01',
};

const MAP_LEVEL = {
    COUNTRY: 'country',
    PROVINCE: 'province',
    MUNICIPALITY: 'municipality',
    BARANGAY: 'barangay',
};

const UNIVERSITY_LANDMARK = {
    lat: 15.63886112631228,
    lng: 120.41524536485072,
    label: 'Tarlac Agricultural University',
};

const PH_REGION_CODES = [
    '010000000',
    '020000000',
    '030000000',
    '040000000',
    '170000000',
    '180000000',
    '050000000',
    '060000000',
    '070000000',
    '080000000',
    '090000000',
    '100000000',
    '110000000',
    '120000000',
    '130000000',
    '140000000',
    '150000000',
    '160000000',
    '190000000',
];

const TARLAC_PROVINCE_CODE = '036900000';
const BORDERING_PROVINCE_CODES = [
    '034900000', // Nueva Ecija
    '035400000', // Pampanga
    '015500000', // Pangasinan
    '037100000', // Zambales
];

const TARLAC_MUNICIPALITY_CODES = [
    '036901000', // Anao
    '036902000', // Bamban
    '036903000', // Camiling
    '036904000', // Capas
    '036905000', // Concepcion
    '036906000', // Gerona
    '036907000', // La Paz
    '036908000', // Mayantoc
    '036909000', // Moncada
    '036910000', // Paniqui
    '036911000', // Pura
    '036912000', // Ramos
    '036913000', // San Clemente
    '036914000', // San Jose
    '036915000', // San Manuel
    '036916000', // City of Tarlac
    '036917000', // Victoria
];

const CUSTOMERS_BY_BARANGAY_PSGC = {
    '036916057': 420, // Poblacion
    '036916024': 365, // Calingcuan
    '036916052': 330, // Matatalaib
    '036916028': 290, // Culipat
    '036916088': 280, // Tibag
    '036916071': 250, // San Pascual
    '036916081': 210, // Sapang Maragul
    '036916076': 190, // Santa Cruz (Alvindia Primero)
};

const CUSTOMERS_BY_MUNICIPALITY_PSGC = {
    '034925000': 185, // San Isidro, Nueva Ecija
    '035410000': 215, // Mabalacat, Pampanga
    '015539000': 170, // San Manuel, Pangasinan
    '037117000': 140, // San Marcelino, Zambales
};

const CUSTOMERS_BY_PROVINCE_PSGC = {
    '051700000': 1100, // Camarines Sur
    '072200000': 980, // Cebu
    '112400000': 920, // Davao del Sur
    '166800000': 760, // Surigao del Norte
};

const CUSTOMERS_BY_COUNTRY_ISO3 = {
    USA: 4200,
    CAN: 1500,
    AUS: 980,
    JPN: 2100,
    SGP: 430,
};

const getBarangayName = (properties = {}) => {
    return (
        properties.barangay_name ||
        properties.BARANGAY ||
        properties.ADM4_EN ||
        properties.NAME ||
        'Barangay'
    );
};

const getMunicipalityName = (properties = {}) => {
    return properties.municity_name || properties.MUNICITY || properties.ADM3_EN || 'Municipality';
};

const getProvinceName = (properties = {}) => {
    return properties.provdist_name || properties.PROVDIST || properties.ADM2_EN || '';
};

const getCountryName = (properties = {}) => properties.name || properties.NAME || 'Country';

const normalizePsgc = (psgc) => String(psgc || '').replace(/\D/g, '');
const normalizeName = (value) => String(value || '').trim().toLowerCase();

const getProvinceCode = (properties = {}) => normalizePsgc(properties.ADM2_PCODE || properties.adm2_psgc || properties.ADM2_PSGC);
const getMunicipalityCode = (properties = {}) => normalizePsgc(properties.ADM3_PCODE || properties.adm3_psgc || properties.ADM3_PSGC);
const getBarangayCode = (properties = {}) => normalizePsgc(properties.ADM4_PCODE || properties.adm4_psgc || properties.ADM4_PSGC);

const getCountryCode = (feature = {}) => {
    return String(feature.id || feature.properties?.ISO_A3 || feature.properties?.iso3 || '').toUpperCase();
};

const withMapMetadata = (feature, level) => ({
    ...feature,
    properties: {
        ...(feature.properties || {}),
        MAP_LEVEL: level,
    },
});

const withCustomerCount = (feature, customerCount) => ({
    ...feature,
    properties: {
        ...(feature.properties || {}),
        CUSTOMER_COUNT: customerCount,
    },
});

const getCustomerCount = (feature = {}) => {
    const properties = feature.properties || {};

    if (typeof properties.CUSTOMER_COUNT === 'number') {
        return properties.CUSTOMER_COUNT;
    }

    const level = properties.MAP_LEVEL;

    if (level === MAP_LEVEL.COUNTRY) {
        return CUSTOMERS_BY_COUNTRY_ISO3[getCountryCode(feature)] || 0;
    }

    if (level === MAP_LEVEL.PROVINCE) {
        return CUSTOMERS_BY_PROVINCE_PSGC[getProvinceCode(properties)] || 0;
    }

    if (level === MAP_LEVEL.MUNICIPALITY) {
        return CUSTOMERS_BY_MUNICIPALITY_PSGC[getMunicipalityCode(properties)] || 0;
    }

    return CUSTOMERS_BY_BARANGAY_PSGC[getBarangayCode(properties)] || 0;
};

const getChoroplethColor = (value) => {
    if (value > 350) return '#1D4ED8';
    if (value > 300) return '#2563EB';
    if (value > 250) return '#3B82F6';
    if (value > 200) return '#60A5FA';
    if (value > 0) return '#93C5FD';
    return '#DBEAFE';
};

const getFeatureArea = (feature) => {
    try {
        return turf.area(feature);
    } catch {
        return 0;
    }
};

const intersectsAny = (candidate, groupFeatures) => {
    return groupFeatures.some((groupFeature) => {
        try {
            return turf.booleanIntersects(candidate, groupFeature);
        } catch {
            return false;
        }
    });
};

const mergeFeatureGroup = (featureGroup) => {
    if (featureGroup.length === 1) {
        return featureGroup[0];
    }

    let merged = featureGroup[0];

    for (let index = 1; index < featureGroup.length; index += 1) {
        const nextFeature = featureGroup[index];
        const collection = turf.featureCollection([merged, nextFeature]);

        try {
            const unioned = turf.union(collection);

            if (unioned) {
                merged = unioned;
                continue;
            }
        } catch {
            // Fall back to simple multi-part combine when a union fails.
        }

        const combined = turf.combine(collection)?.features?.[0];
        if (combined) {
            merged = combined;
        }
    }

    return merged;
};

const getGroupingThresholds = (features) => {
    const bantog = features.find((feature) => {
        const barangay = normalizeName(getBarangayName(feature.properties));
        const municipality = normalizeName(getMunicipalityName(feature.properties));
        return barangay === 'bantog' && municipality.includes('tarlac');
    });

    const bueno = features.find((feature) => {
        const barangay = normalizeName(getBarangayName(feature.properties));
        const municipality = normalizeName(getMunicipalityName(feature.properties));
        return barangay === 'bueno' && municipality.includes('capas');
    });

    const areas = features.map((feature) => getFeatureArea(feature)).filter((area) => area > 0).sort((a, b) => a - b);

    const fallbackMin = areas[Math.floor(areas.length * 0.2)] || 0;
    const fallbackMax = areas[Math.floor(areas.length * 0.6)] || Math.max(fallbackMin * 2, 1);

    const minArea = bantog ? getFeatureArea(bantog) : fallbackMin;
    const maxArea = bueno ? getFeatureArea(bueno) : fallbackMax;

    return {
        minArea,
        maxArea: Math.max(maxArea, minArea),
    };
};

const groupSmallBarangays = (features) => {
    const { minArea, maxArea } = getGroupingThresholds(features);
    const assigned = new Set();
    const groupedOutput = [];
    const municipalityBuckets = new Map();

    features.forEach((feature, index) => {
        const municipalityName = getMunicipalityName(feature.properties);
        const municipalityKey = normalizeName(municipalityName);
        const item = {
            index,
            feature,
            area: getFeatureArea(feature),
            customerCount: getCustomerCount(feature),
            barangayName: getBarangayName(feature.properties),
            municipalityName,
            provinceName: getProvinceName(feature.properties),
        };

        if (!municipalityBuckets.has(municipalityKey)) {
            municipalityBuckets.set(municipalityKey, []);
        }

        municipalityBuckets.get(municipalityKey).push(item);
    });

    municipalityBuckets.forEach((items) => {
        const sorted = [...items].sort((a, b) => a.area - b.area);

        sorted.forEach((seed) => {
            if (assigned.has(seed.index) || seed.area >= minArea) {
                return;
            }

            const members = [seed];
            assigned.add(seed.index);

            let runningArea = seed.area;
            let runningCustomers = seed.customerCount;

            while (runningArea < minArea) {
                const nextMember = sorted
                    .filter((candidate) => {
                        if (assigned.has(candidate.index)) {
                            return false;
                        }

                        if (runningArea + candidate.area > maxArea) {
                            return false;
                        }

                        return intersectsAny(candidate.feature, members.map((member) => member.feature));
                    })
                    .sort((a, b) => a.area - b.area)[0];

                if (!nextMember) {
                    break;
                }

                assigned.add(nextMember.index);
                members.push(nextMember);
                runningArea += nextMember.area;
                runningCustomers += nextMember.customerCount;
            }

            if (members.length <= 1) {
                assigned.delete(seed.index);
                return;
            }

            const mergedGeometry = mergeFeatureGroup(members.map((member) => member.feature));
            const mergedNames = members.map((member) => member.barangayName);

            groupedOutput.push(withMapMetadata({
                ...mergedGeometry,
                properties: {
                    ...members[0].feature.properties,
                    BARANGAY: `Grouped (${members.length})`,
                    GROUPED_BARANGAY_NAMES: mergedNames.join(', '),
                    GROUPED_BARANGAY_COUNT: members.length,
                    CUSTOMER_COUNT: runningCustomers,
                },
            }, MAP_LEVEL.BARANGAY));
        });

        items.forEach((item) => {
            if (assigned.has(item.index)) {
                return;
            }

            groupedOutput.push(withMapMetadata({
                ...item.feature,
                properties: {
                    ...item.feature.properties,
                    CUSTOMER_COUNT: item.customerCount,
                    GROUPED_BARANGAY_COUNT: 1,
                },
            }, MAP_LEVEL.BARANGAY));
        });
    });

    return groupedOutput;
};

const loadBarangayGeoJson = async () => {
    const fileSuffix = BARANGAY_FILE_SUFFIX_BY_RESOLUTION[BARANGAY_MAP_RESOLUTION] || '0.01';

    const requests = TARLAC_MUNICIPALITY_CODES.map(async (municipalityCode) => {
        const response = await fetch(`${BARANGAY_GEOJSON_BASE_URL}/barangays-municity-ph${municipalityCode}.${fileSuffix}.json`);

        if (!response.ok) {
            return null;
        }

        return response.json();
    });

    const collections = await Promise.all(requests);
    const features = collections.flatMap((collection) => collection?.features || []);

    if (!features.length) {
        throw new Error('Failed to fetch Tarlac barangay boundaries.');
    }

    const groupedFeatures = groupSmallBarangays(features);

    return {
        type: 'FeatureCollection',
        features: groupedFeatures,
    };
};

const loadMunicipalityGeoJson = async (provinceCode) => {
    const response = await fetch(`${MUNICTIES_GEOJSON_BASE_URL}/municities-province-ph${provinceCode}.0.01.json`);

    if (!response.ok) {
        return null;
    }

    return response.json();
};

const loadProvinceGeoJson = async (regionCode) => {
    const response = await fetch(`${PROVINCES_GEOJSON_BASE_URL}/provinces-region-ph${regionCode}.0.01.json`);

    if (!response.ok) {
        return null;
    }

    return response.json();
};

const loadWorldCountriesGeoJson = async () => {
    const response = await fetch(COUNTRY_WORLD_GEOJSON_URL);

    if (!response.ok) {
        throw new Error('Failed to load world country boundaries.');
    }

    return response.json();
};

const loadHierarchicalGeoJson = async () => {
    const [worldCountries, tarlacBarangays, provinceCollections, municipalityCollections, tarlacMunicipalities] = await Promise.all([
        loadWorldCountriesGeoJson(),
        loadBarangayGeoJson(),
        Promise.all(PH_REGION_CODES.map((regionCode) => loadProvinceGeoJson(regionCode))),
        Promise.all(BORDERING_PROVINCE_CODES.map((provinceCode) => loadMunicipalityGeoJson(provinceCode))),
        loadMunicipalityGeoJson(TARLAC_PROVINCE_CODE),
    ]);

    const worldFeatures = (worldCountries.features || [])
        .filter((feature) => getCountryCode(feature) !== 'PHL')
        .map((feature) => withMapMetadata(withCustomerCount(feature, getCustomerCount(withMapMetadata(feature, MAP_LEVEL.COUNTRY))), MAP_LEVEL.COUNTRY));

    const provinceExclusionSet = new Set([TARLAC_PROVINCE_CODE, ...BORDERING_PROVINCE_CODES]);
    const provinceFeatures = provinceCollections
        .flatMap((collection) => collection?.features || [])
        .map((feature) => withMapMetadata(feature, MAP_LEVEL.PROVINCE))
        .filter((feature) => !provinceExclusionSet.has(getProvinceCode(feature.properties)))
        .map((feature) => withCustomerCount(feature, getCustomerCount(feature)));

    const municipalityFeatures = municipalityCollections
        .flatMap((collection) => collection?.features || [])
        .map((feature) => withMapMetadata(feature, MAP_LEVEL.MUNICIPALITY))
        .map((feature) => withCustomerCount(feature, getCustomerCount(feature)));

    const barangayFeatures = (tarlacBarangays.features || []).map((feature) => withCustomerCount(feature, getCustomerCount(feature)));

    const barangayMunicipalityCodes = new Set(
        barangayFeatures
            .map((feature) => getMunicipalityCode(feature.properties))
            .filter(Boolean)
    );

    const tarlacMunicipalityFallbackFeatures = (tarlacMunicipalities?.features || [])
        .map((feature) => withMapMetadata(feature, MAP_LEVEL.MUNICIPALITY))
        .filter((feature) => !barangayMunicipalityCodes.has(getMunicipalityCode(feature.properties)))
        .map((feature) => withCustomerCount(feature, getCustomerCount(feature)));

    const features = [
        ...worldFeatures,
        ...provinceFeatures,
        ...municipalityFeatures,
        ...tarlacMunicipalityFallbackFeatures,
        ...barangayFeatures,
    ];

    if (!features.length) {
        throw new Error('No map boundaries were loaded.');
    }

    return {
        type: 'FeatureCollection',
        features,
    };
};

let hierarchicalGeoJsonPromise;

const loadHierarchicalGeoJsonCached = () => {
    if (!hierarchicalGeoJsonPromise) {
        hierarchicalGeoJsonPromise = loadHierarchicalGeoJson();
    }

    return hierarchicalGeoJsonPromise;
};

const getFeaturePlaceLabel = (feature) => {
    const properties = feature.properties || {};
    const level = properties.MAP_LEVEL;

    if (level === MAP_LEVEL.COUNTRY) {
        return getCountryName(properties);
    }

    if (level === MAP_LEVEL.PROVINCE) {
        return `${getProvinceName(properties)}, Philippines`;
    }

    if (level === MAP_LEVEL.MUNICIPALITY) {
        return `${getMunicipalityName(properties)}, ${getProvinceName(properties)}`;
    }

    const barangayName = getBarangayName(properties);
    const municipalityName = getMunicipalityName(properties);
    const provinceName = getProvinceName(properties);

    return provinceName
        ? `${barangayName}, ${municipalityName}, ${provinceName}`
        : `${barangayName}, ${municipalityName}`;
};

const getBorderWeightByLevel = (feature) => {
    const level = feature.properties?.MAP_LEVEL;

    if (level === MAP_LEVEL.COUNTRY) return 0.6;
    if (level === MAP_LEVEL.PROVINCE) return 0.8;
    if (level === MAP_LEVEL.MUNICIPALITY) return 1;
    return 1.1;
};

const getLegendMaxValue = () => {
    const values = [
        ...Object.values(CUSTOMERS_BY_BARANGAY_PSGC),
        ...Object.values(CUSTOMERS_BY_MUNICIPALITY_PSGC),
        ...Object.values(CUSTOMERS_BY_PROVINCE_PSGC),
        ...Object.values(CUSTOMERS_BY_COUNTRY_ISO3),
    ];

    return Math.max(...values);
};

const addLegend = (mapInstance) => {
    const legend = L.control({ position: 'bottomright' });

    legend.onAdd = () => {
        const div = L.DomUtil.create('div', 'info legend');
        div.style.background = 'rgba(255,255,255,0.95)';
        div.style.padding = '8px 10px';
        div.style.borderRadius = '8px';
        div.style.boxShadow = '0 2px 8px rgba(15, 23, 42, 0.15)';
        div.style.fontSize = '12px';
        div.style.lineHeight = '1.4';

        const maxValue = getLegendMaxValue();

        div.innerHTML = `
            <strong>Customers</strong>
            <div style="margin-top:6px; width:160px; height:10px; border-radius:999px; background:linear-gradient(90deg, #93C5FD 0%, #60A5FA 35%, #3B82F6 60%, #2563EB 80%, #1D4ED8 100%);"></div>
            <div style="display:flex; justify-content:space-between; margin-top:4px; color:#334155;">
                <span>0</span>
                <span>${maxValue.toLocaleString()}</span>
            </div>
        `;

        return div;
    };

    legend.addTo(mapInstance);
};

const formatGroupedBarangayTooltip = (groupedNames, groupedCount) => {
    if (groupedCount <= 1 || !groupedNames) {
        return '';
    }

    const names = String(groupedNames)
        .split(',')
        .map((name) => name.trim())
        .filter(Boolean);

    if (!names.length) {
        return `<br>Includes: ${groupedCount} barangays`;
    }

    const previewNames = names.slice(0, 3).join(', ');
    const remainingCount = names.length - 3;
    const remainderLabel = remainingCount > 0 ? ` +${remainingCount} more` : '';

    return `<br>Includes: ${previewNames}${remainderLabel}`;
};

const addBoundariesLayer = (mapInstance, hierarchicalGeoJson) => {
    return L.geoJSON(hierarchicalGeoJson, {
        style: (feature) => {
            const customerCount = getCustomerCount(feature);

            return {
                color: '#1E3A8A',
                weight: getBorderWeightByLevel(feature),
                fillColor: getChoroplethColor(customerCount),
                fillOpacity: 0.75,
            };
        },
        onEachFeature: (feature, layer) => {
            const properties = feature.properties || {};
            const customerCount = getCustomerCount(feature);
            const placeLabel = getFeaturePlaceLabel(feature);
            const groupedCount = properties.GROUPED_BARANGAY_COUNT || 1;
            const groupedNames = properties.GROUPED_BARANGAY_NAMES;
            const customerLabel = customerCount > 0
                ? `${customerCount.toLocaleString()} members.`
                : 'No recorded members.';
            const groupedLabel = formatGroupedBarangayTooltip(groupedNames, groupedCount);

            layer.bindTooltip(`${placeLabel}<br>${customerLabel}${groupedLabel}`, { sticky: true });
        },
    }).addTo(mapInstance);
};

const addUniversityLandmark = (mapInstance) => {
    const universityIcon = L.divIcon({
        className: 'university-landmark-icon',
        html: `
            <div style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:999px; background:#0f172a; border:2px solid #ffffff; box-shadow:0 2px 8px rgba(15,23,42,0.35);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M3 10.2L12 5L21 10.2V12H3V10.2Z" fill="#F8FAFC"/>
                    <rect x="5" y="12" width="2.4" height="6" rx="0.4" fill="#F8FAFC"/>
                    <rect x="10.8" y="12" width="2.4" height="6" rx="0.4" fill="#F8FAFC"/>
                    <rect x="16.6" y="12" width="2.4" height="6" rx="0.4" fill="#F8FAFC"/>
                    <rect x="4" y="18.2" width="16" height="1.8" rx="0.4" fill="#F8FAFC"/>
                </svg>
            </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    });

    L.marker([UNIVERSITY_LANDMARK.lat, UNIVERSITY_LANDMARK.lng], { icon: universityIcon })
        .addTo(mapInstance)
        .bindTooltip(UNIVERSITY_LANDMARK.label, { direction: 'top', offset: [0, -12] });
};

const createDemographicMap = (mapElement) => {
    const mapInstance = L.map(mapElement, {
        zoomControl: true,
        minZoom: 2,
        maxZoom: 13,
    }).setView([15.5, 120.55], 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(mapInstance);

    addUniversityLandmark(mapInstance);

    loadHierarchicalGeoJsonCached()
        .then((hierarchicalGeoJson) => {
            addBoundariesLayer(mapInstance, hierarchicalGeoJson);
            addLegend(mapInstance);
        })
        .catch(() => {
            L.popup()
                .setLatLng([15.5, 120.55])
                .setContent('Unable to load map boundary data right now.')
                .openOn(mapInstance);
        });

    return mapInstance;
};

export const initMap = () => {
    const mapElement = document.querySelector('#mapOne');
    const modalMapElement = document.querySelector('#mapOneModal');

    if (mapElement) {
        createDemographicMap(mapElement);
    }

    if (modalMapElement) {
        let modalMap;

        window.addEventListener('demographic-map-modal-opened', () => {
            if (!modalMap) {
                modalMap = createDemographicMap(modalMapElement);
            }

            modalMap.invalidateSize();
            setTimeout(() => modalMap.invalidateSize(), 220);
        });
    }
};

export default initMap;
