/**
 * mapa-sismos.js
 * -------------------------------------------------------------------
 * Lógica de mapa (Leaflet + OpenStreetMap) para el visor sísmico:
 *   - inicializarMapaListado(): pinta todos los sismos + ubicación del usuario
 *   - inicializarMapaDetalle(): centra el mapa en un solo epicentro
 *   - inicializarMapaSeleccion(): permite elegir el epicentro haciendo clic
 *
 * La ubicación del usuario se obtiene con navigator.geolocation y NUNCA
 * se envía al servidor ni se guarda en base de datos: solo vive en el
 */

// El tamaño representa la magnitud y el color comunica el nivel de energía.
function mt(clave) {
    const diccionario = (window.SIGRA_I18N && window.SIGRA_I18N.map) || {};
    let texto = diccionario[clave] || clave;

    for (let i = 1; i < arguments.length; i += 1) {
        texto = texto.replace(new RegExp('\\{' + (i - 1) + '\\}', 'g'), arguments[i]);
    }

    return texto;
}

function estiloEpicentro(sismo) {
    const magnitud = Number(sismo.magnitud) || 0;
    const profundidad = Number(sismo.profundidad) || 0;
    const radio = Math.max(3.5, Math.min(10.5, 2.5 + magnitud * 1.4));
    let color = '#00d639';

    if (magnitud >= 4.5) {
        color = '#f20d19';
    } else if (magnitud >= 3.5) {
        color = '#ffb800';
    } else if (magnitud >= 2.5) {
        color = '#ffe500';
    }

    return {
        radius: radio,
        color: profundidad >= 70 ? '#1019d6' : '#ffffff',
        fillColor: profundidad >= 70 ? '#1019d6' : color,
        fillOpacity: 0.76,
        opacity: 0.9,
        weight: profundidad >= 70 ? 2.2 : 1.2,
    };
}

function marcadorEpicentro(sismo) {
    return L.circleMarker([sismo.lat, sismo.lng], estiloEpicentro(sismo));
}

function iconoEpicentro() {
    return L.divIcon({
        html: window.SIGRA_ICONS.pin,
        className: 'icono-vector',
        iconSize: [24, 24],
        iconAnchor: [12, 24],
    });
}

function iconoUsuario() {
    return L.divIcon({
        html: window.SIGRA_ICONS.home,
        className: 'icono-vector',
        iconSize: [24, 24],
        iconAnchor: [12, 22],
    });
}

/**
 * Fórmula de Haversine: distancia aproximada en km entre dos coordenadas.
 */
function calcularDistanciaKm(lat1, lon1, lat2, lon2) {
    const R = 6371; // radio de la Tierra en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

const opcionesGeolocalizacion = {
    enableHighAccuracy: true,
    maximumAge: 60000,
    timeout: 15000,
};

// Cada intento termina, incluso si el proveedor por IP no responde.
function obtenerUbicacionUsuario() {
    function ubicacionPorIp(error) {
        if (typeof fetch !== 'function') return Promise.reject(error);
        const controller = new AbortController();
        const timeout = setTimeout(function () { controller.abort(); }, 7000);
        return fetch('https://ipapi.co/json/', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(function (respuesta) {
                if (!respuesta.ok) throw error;
                return respuesta.json();
            })
            .then(function (datos) {
                if (!Number.isFinite(datos.latitude) || !Number.isFinite(datos.longitude)
                    || Math.abs(datos.latitude) > 90 || Math.abs(datos.longitude) > 180) {
                    throw error;
                }
                return {
                    coords: { latitude: datos.latitude, longitude: datos.longitude, accuracy: 2000 },
                };
            })
            .finally(function () { clearTimeout(timeout); });
    }

    if (!navigator.geolocation) {
        return ubicacionPorIp(new Error(mt('geoUnsupported')));
    }
    return new Promise(function (resolve, reject) {
        navigator.geolocation.getCurrentPosition(resolve, reject, opcionesGeolocalizacion);
    }).catch(ubicacionPorIp);
}

function nivelIntensidad(valor) {
    const numero = Number(valor);
    if (!Number.isFinite(numero)) {
        return mt('intensityUnavailable');
    }
    if (numero >= 8) {
        return mt('intensityVeryStrong');
    }
    if (numero >= 6) {
        return mt('intensityStrong');
    }
    if (numero >= 4) {
        return mt('intensityModerate');
    }
    if (numero >= 2) {
        return mt('intensityLight');
    }
    return mt('intensityVeryLight');
}

function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, function (caracter) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[caracter];
    });
}

function popupSismo(s, distanciaTexto) {
    const intensidadPercibida = s.intensidadPercibida ?? mt('noData');
    const intensidadInstrumental = s.intensidadInstrumental ?? mt('noData');
    const aceleracion = s.aceleracion ?? mt('noData');
    const fuente = s.fuente || 'SGC';
    const enlace = s.url ? `<br><a href="${escaparHtml(s.url)}" target="_blank" rel="noopener">${escaparHtml(mt('viewOfficial'))}</a>` : '';

    return `
        <strong>${escaparHtml(mt('popupQuake', s.id))}</strong><br>
        ${escaparHtml(mt('popupSource', fuente))}<br>
        ${escaparHtml(mt('popupMagnitude', s.magnitud))}<br>
        ${escaparHtml(mt('popupDepth', s.profundidad))}<br>
        ${escaparHtml(mt('popupLocation', s.ubicacion))}<br>
        ${escaparHtml(mt('popupDate', s.fecha_hora))}<br>
        ${escaparHtml(mt('popupFelt', intensidadPercibida, nivelIntensidad(intensidadPercibida)))}<br>
        ${escaparHtml(mt('popupInstrumental', intensidadInstrumental, nivelIntensidad(intensidadInstrumental)))}<br>
        ${escaparHtml(mt('popupPga', aceleracion))}
        ${distanciaTexto ? '<br>' + escaparHtml(distanciaTexto) : ''}
        ${Number(s.citizen_reports_count) > 0 ? '<br>' + escaparHtml(mt('reportedByUsers', Number(s.citizen_reports_count))) : ''}
        ${enlace}
    `;
}

/**
 * Mapa del listado: pinta todos los sismos y, si el usuario da permiso,
 * su ubicación actual, calculando la distancia a cada epicentro.
 */
let estadoMapaListado = null;

function pintarMarcadoresListado(sismos, ajustarVista) {
    if (!estadoMapaListado) {
        return;
    }

    estadoMapaListado.sismos = sismos;
    estadoMapaListado.capaSismos.clearLayers();

    const marcadores = [];
    sismos.forEach(function (s) {
        const marker = marcadorEpicentro(s)
            .addTo(estadoMapaListado.capaSismos)
            .bindPopup(popupSismo(s));
        marcadores.push(marker);
    });

    if (ajustarVista && marcadores.length > 0) {
        const grupo = L.featureGroup(marcadores);
        estadoMapaListado.mapa.fitBounds(grupo.getBounds().pad(0.2));
    }
}

function agregarControlCapasMapa(mapa) {
    const contenedor = document.createElement('div');
    contenedor.style.cssText = 'position:absolute; top:12px; right:12px; z-index:500; display:flex; gap:8px; padding:6px; border-radius:999px; background:rgba(15, 23, 42, 0.78); box-shadow:0 10px 25px rgba(15, 23, 42, 0.18); backdrop-filter:blur(4px);';

    const capas = {
        osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18,
        }),
        cartoDark: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ',
            maxZoom: 16,
        }),
        satelite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 16,
        }),
        topo: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data: &copy; OpenStreetMap contributors, SRTM | Map style: &copy; OpenTopoMap',
            maxZoom: 17,
        }),
    };

    const botones = [
        { id: 'osm', label: mt('layerMap') },
        { id: 'cartoDark', label: mt('layerDark') },
        { id: 'satelite', label: mt('layerSatellite') },
        { id: 'topo', label: mt('layerTopo') },
    ];

    const clavePersistencia = 'sismos-mapa-capa';
    const temaOscuro = document.documentElement.dataset.bsTheme === 'dark';
    let capaInicial = temaOscuro ? 'cartoDark' : 'osm';
    try { capaInicial = localStorage.getItem(clavePersistencia) || capaInicial; } catch (_) { /* Almacenamiento bloqueado. */ }

    function aplicarCapa(capaActiva) {
        Object.entries(capas).forEach(function ([key, capa]) {
            if (key === capaActiva) {
                if (!mapa.hasLayer(capa)) {
                    capa.addTo(mapa);
                }
            } else if (mapa.hasLayer(capa)) {
                mapa.removeLayer(capa);
            }
        });

        Array.from(contenedor.querySelectorAll('button')).forEach(function (buttonElemento) {
            const activo = buttonElemento.dataset.capa === capaActiva;
            buttonElemento.style.background = activo ? 'rgba(34,197,94,0.95)' : 'rgba(255,255,255,0.16)';
            buttonElemento.style.color = activo ? '#052e16' : '#f8fafc';
        });

        try { localStorage.setItem(clavePersistencia, capaActiva); } catch (_) { /* La capa sigue disponible. */ }
    }

    botones.forEach(function (botonConfig) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = botonConfig.label;
        boton.dataset.capa = botonConfig.id;
        boton.style.cssText = 'border:0; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; background:rgba(255,255,255,0.16); color:#f8fafc;';
        boton.setAttribute('aria-label', mt('layerChange', botonConfig.label));
        boton.addEventListener('click', function () {
            aplicarCapa(botonConfig.id);
        });
        contenedor.appendChild(boton);
    });

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'map-layer-toggle';
    toggle.setAttribute('aria-label', mt('collapseLayers'));
    toggle.setAttribute('aria-expanded', 'true');
    toggle.innerHTML = '<span aria-hidden="true">›</span>';
    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const abierto = !contenedor.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', String(abierto));
        toggle.setAttribute('aria-label', abierto ? mt('collapseLayers') : mt('expandLayers'));
        toggle.innerHTML = abierto ? '<span aria-hidden="true">›</span>' : '<span aria-hidden="true">‹</span>';
    });
    contenedor.insertBefore(toggle, contenedor.firstChild);

    const mapaContenedor = mapa.getContainer();
    mapaContenedor.style.position = 'relative';
    if (!mapaContenedor.querySelector('.map-layer-switch')) {
        contenedor.classList.add('map-layer-switch');
        mapaContenedor.appendChild(contenedor);
    }

    aplicarCapa(capaInicial);
    return capas;
}

function actualizarDistanciaListado() {
    if (!estadoMapaListado) {
        return;
    }

    const elementosDistancia = document.querySelectorAll('.evento-item[data-lat][data-lng] .distancia-evento');

    if (!estadoMapaListado.posicionUsuario) {
        elementosDistancia.forEach(function (elemento) {
            elemento.textContent = mt('activateLocation');
        });
        return;
    }

    const userLat = estadoMapaListado.posicionUsuario.lat;
    const userLng = estadoMapaListado.posicionUsuario.lng;

    elementosDistancia.forEach(function (elemento) {
        const item = elemento.closest('.evento-item');
        if (!item) {
            return;
        }
        const distancia = calcularDistanciaKm(
            userLat,
            userLng,
            Number(item.dataset.lat),
            Number(item.dataset.lng)
        );
        elemento.textContent = mt('kmFromYou', distancia.toFixed(1));
    });
}

function actualizarMapaListado(sismos) {
    pintarMarcadoresListado(sismos, false);
    actualizarDistanciaListado();
}

function inicializarMapaListado(elementId, sismos, elementoDistanciaId) {
    const centroDefault = [20, 0]; // vista mundial
    const mapa = L.map(elementId, { worldCopyJump: true, minZoom: 2 }).setView(centroDefault, 2);
    const capaSismos = L.layerGroup().addTo(mapa);
    const contenedorDistancia = document.getElementById(elementoDistanciaId);

    estadoMapaListado = {
        mapa: mapa,
        capaSismos: capaSismos,
        sismos: sismos,
        posicionUsuario: null,
        usuarioMarker: null,
        precisionUsuario: null,
        circuloRadio: null,
        contenedorDistancia: contenedorDistancia,
    };

    agregarControlCapasMapa(mapa);
    pintarMarcadoresListado(sismos, false);

    // La geolocalización no se solicita automáticamente: el usuario decide
    // con el botón "Mi ubicación". Así el mapa funciona igual sin permisos.
    if (contenedorDistancia) {
        contenedorDistancia.textContent = mt('activateLocation');
    }
    actualizarDistanciaListado();
}

/**
 * Fija (o actualiza) la ubicación del usuario en el mapa del listado.
 */
function fijarUbicacionListado(lat, lng, precision) {
    if (!estadoMapaListado) {
        return;
    }

    estadoMapaListado.posicionUsuario = { lat: lat, lng: lng };
    estadoMapaListado.precisionUsuario = precision;

    const mapa = estadoMapaListado.mapa;
    const popup = `${window.SIGRA_ICONS.home} ${mt('yourLocation', Math.round(precision || 0))}`;

    if (estadoMapaListado.usuarioMarker) {
        estadoMapaListado.usuarioMarker.setLatLng([lat, lng]).setPopupContent(popup);
    } else {
        estadoMapaListado.usuarioMarker = L.marker([lat, lng], { icon: iconoUsuario() })
            .addTo(mapa)
            .bindPopup(popup);
    }

    actualizarDistanciaListado();
}

/**
 * Ajusta la vista del listado a un conjunto de eventos.
 */
function ajustarVistaListado(sismos) {
    if (!estadoMapaListado) {
        return;
    }

    const mapa = estadoMapaListado.mapa;
    const puntos = (sismos || [])
        .map(function (sismo) { return [Number(sismo.lat), Number(sismo.lng)]; })
        .filter(function (punto) { return Number.isFinite(punto[0]) && Number.isFinite(punto[1]); });

    if (puntos.length === 0) {
        mapa.setView([20, 0], 2);

        return;
    }

    mapa.fitBounds(L.latLngBounds(puntos).pad(0.15), { maxZoom: 8, animate: false });
}

/**
 * Dibuja el círculo del radio activo y, opcionalmente, ajusta la vista.
 */
function dibujarRadioListado(centro, radioKm, ajustarVista) {
    if (!estadoMapaListado) {
        return;
    }

    const mapa = estadoMapaListado.mapa;

    if (estadoMapaListado.circuloRadio) {
        mapa.removeLayer(estadoMapaListado.circuloRadio);
        estadoMapaListado.circuloRadio = null;
    }

    if (!centro || !radioKm || radioKm <= 0) {
        if (ajustarVista) {
            ajustarVistaListado(estadoMapaListado.sismos);
        }
        return;
    }

    const circulo = L.circle([centro.lat, centro.lng], {
        radius: radioKm * 1000,
        color: '#087f78',
        weight: 1.5,
        fillColor: '#087f78',
        fillOpacity: 0.08,
    }).addTo(mapa);

    estadoMapaListado.circuloRadio = circulo;

    if (ajustarVista) {
        mapa.fitBounds(circulo.getBounds(), { padding: [20, 20], animate: false });
    }
}

/**
 * Mapa de detalle: centra en un solo epicentro y muestra distancia
 * desde la ubicación actual del usuario.
 */
function inicializarMapaDetalle(elementId, sismo, elementoDistanciaId) {
    const mapa = L.map(elementId).setView([sismo.lat, sismo.lng], 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(mapa);

    marcadorEpicentro(sismo)
        .addTo(mapa)
        .bindPopup(popupSismo(sismo))
        .openPopup();

    const contenedorDistancia = document.getElementById(elementoDistanciaId);

    if (!navigator.geolocation) {
        if (contenedorDistancia) {
            contenedorDistancia.textContent = mt('geoUnsupported');
        }
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const userLat = pos.coords.latitude;
            const userLng = pos.coords.longitude;

            L.marker([userLat, userLng], { icon: iconoUsuario() })
                .addTo(mapa)
                .bindPopup(`${window.SIGRA_ICONS.home} ${mt('yourLocation', Math.round(pos.coords.accuracy))}`);

            const d = calcularDistanciaKm(userLat, userLng, sismo.lat, sismo.lng);
            if (contenedorDistancia) {
                contenedorDistancia.textContent = `Distancia aproximada: ${d.toFixed(1)} km`;
            }
        },
        function () {
            if (contenedorDistancia) {
                contenedorDistancia.textContent = mt('geoDenied');
            }
        },
        opcionesGeolocalizacion
    );
}

/**
 * Mapa de selección: usado en create/edit para elegir el epicentro
 * haciendo clic en el mapa, rellenando los inputs de latitud/longitud.
 */
function inicializarMapaSeleccion(elementId, inputLatId, inputLngId) {
    const inputLat = document.getElementById(inputLatId);
    const inputLng = document.getElementById(inputLngId);
    const estadoUbicacion = document.getElementById('estado-ubicacion');

    const latInicial = parseFloat(inputLat.value) || 4.5709;
    const lngInicial = parseFloat(inputLng.value) || -74.2973;

    const mapa = L.map(elementId).setView([latInicial, lngInicial], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(mapa);

    let marcador = null;

    function marcarUbicacion(lat, lng, zoom) {
        inputLat.value = lat.toFixed(6);
        inputLng.value = lng.toFixed(6);

        if (marcador) {
            marcador.setLatLng([lat, lng]);
        } else {
            marcador = L.marker([lat, lng], { icon: iconoEpicentro() }).addTo(mapa);
        }

        if (zoom) {
            mapa.setView([lat, lng], zoom);
        }
    }

    if (inputLat.value && inputLng.value) {
        marcador = L.marker([latInicial, lngInicial], { icon: iconoEpicentro() }).addTo(mapa);
    }

    mapa.on('click', function (e) {
        const { lat, lng } = e.latlng;
        marcarUbicacion(lat, lng);
        if (estadoUbicacion) {
            estadoUbicacion.textContent = mt('pointSelected');
        }
    });

    const btn = document.getElementById('btn-usar-mapa');
    if (btn) {
        btn.addEventListener('click', function () {
            document.getElementById(elementId).scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    const btnMiUbicacion = document.getElementById('btn-mi-ubicacion');
    if (btnMiUbicacion) {
        btnMiUbicacion.addEventListener('click', function () {
            if (!navigator.geolocation) {
                if (estadoUbicacion) {
                    estadoUbicacion.textContent = mt('geoNotAllowed');
                }
                return;
            }

            btnMiUbicacion.disabled = true;
            if (estadoUbicacion) {
                estadoUbicacion.textContent = mt('searchingLocation');
            }

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    marcarUbicacion(pos.coords.latitude, pos.coords.longitude, 13);
                    if (estadoUbicacion) {
                        estadoUbicacion.textContent = mt('locationFound');
                    }
                    btnMiUbicacion.disabled = false;
                },
                function () {
                    if (estadoUbicacion) {
                        estadoUbicacion.textContent = mt('locationFailed');
                    }
                    btnMiUbicacion.disabled = false;
                }
            );
        });
    }
}
