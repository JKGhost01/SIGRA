/* GEM Global Seismic Hazard Map v2026.1: escala mundial fija, independiente del catálogo. */
'use strict';
(() => {
    // Endpoint publicado por el visor oficial https://maps.openquake.org/map/gshm-v2026_1/.
    const url = 'https://maps.openquake.org/mapproxy/gshm-2026-06-04-nobg/wmts/v20260/webmercator/{z}/{x}/{y}.png';
    const credit = '© GEM Foundation · Global Seismic Hazard Map v2026.1 · CC BY-NC-SA 4.0';
    const maximumLevel = 8;
    window.SIGRA_HAZARD = {
        url, credit, maximumLevel,
        leaflet(L, onError) {
            const layer = L.tileLayer(url, { attribution: credit, opacity: 0.72,
                minZoom: 0, maxNativeZoom: maximumLevel, maxZoom: 22,
                noWrap: true, bounds: [[-85.05112878, -180], [85.05112878, 180]],
                updateWhenIdle: true, keepBuffer: 1,
            });
            layer.on('tileerror', onError);
            return layer;
        },
        cesium(C, onError) {
            const provider = new C.UrlTemplateImageryProvider({ url, credit, maximumLevel,
                tilingScheme: new C.WebMercatorTilingScheme(), enablePickFeatures: false,
            });
            provider.errorEvent.addEventListener(onError);
            return provider;
        },
    };
})();
