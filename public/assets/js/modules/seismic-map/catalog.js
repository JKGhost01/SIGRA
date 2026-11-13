'use strict';
(() => {
    const T = SIGRA_MAP_TEXT;
    function tr(clave) {
        let texto = T[clave] || clave;

        for (let i = 1; i < arguments.length; i += 1) {
            texto = texto.replace(new RegExp('\\{' + (i - 1) + '\\}', 'g'), arguments[i]);
        }

        return texto;
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

    function fechaLocalTimestamp(valor) {
        const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(valor));
        if (!partes) {
            return 0;
        }
        return Date.UTC(
            Number(partes[1]), Number(partes[2]) - 1, Number(partes[3]),
            Number(partes[4]) + 5, Number(partes[5]), Number(partes[6] || 0)
        );
    }

    function formatearFechaLocal(valor) {
        const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(valor));
        if (!partes) {
            return valor;
        }
        const meses = T.months;
        return `${partes[3]} ${meses[Number(partes[2]) - 1]} ${partes[1]} · ${partes[4]}:${partes[5]}`;
    }

    const idsConocidos = new Set(SISMOS.map(function (evento) { return String(evento.id); }));
    let eventosActuales = SISMOS;
    let idsNuevos = new Set();
    let resaltadoNuevoActivo = true;
    let temporizadorResaltadoNuevo;
    let temporizadorAvisoNuevo;
    let temporizadorDatosActualizados;
    let replayTimer = null;
    let posicionUsuario = null;
    function leerRadioGuardado() {
        try { return localStorage.getItem('sigra-radio-km'); } catch (_) { return null; }
    }
    function guardarRadio(valor) {
        radioElegido = true;
        try {
            localStorage.setItem('sigra-radio-km', String(valor));
        } catch (_) { /* Los filtros funcionan también sin almacenamiento. */ }
    }
    const radioGuardado = leerRadioGuardado();
    let radioElegido = radioGuardado !== null;
    let radioKm = Number(radioGuardado) || 0;
    let ubicacionPendiente = false;
    let filtroFuente = '';
    let filtroMagnitud = '';
    let selectorFuente;
    let selectorMagnitud;
    let selectorRadio;
    let botonUbicacion;
    let estadoUbicacion;

    function mostrarAviso(elemento, mensaje, temporizador) {
        elemento.textContent = mensaje;
        elemento.classList.add('visible');
        window.clearTimeout(temporizador);
        return window.setTimeout(function () { elemento.classList.remove('visible'); }, 6000);
    }

    function iniciarTemporizadorResaltado() {
        temporizadorResaltadoNuevo = window.setTimeout(function () {
            resaltadoNuevoActivo = false;
            document.querySelectorAll('.evento-item.evento-nuevo').forEach(function (evento) {
                evento.classList.remove('evento-nuevo');
            });
        }, 60000);
    }

    function distanciaDeEvento(evento) {
        if (!posicionUsuario) {
            return null;
        }

        return calcularDistanciaKm(
            posicionUsuario.lat,
            posicionUsuario.lng,
            Number(evento.lat),
            Number(evento.lng)
        );
    }

    function eventosVisibles() {
        const filtro = filtroMunicipio ? filtroMunicipio.value.trim().toLowerCase() : '';

        return eventosActuales.filter(function (evento) {
            if (filtro && !String(evento.municipios || evento.ubicacion).toLowerCase().includes(filtro)) {
                return false;
            }
            if (filtroFuente && String(evento.fuente || 'SGC') !== filtroFuente) {
                return false;
            }
            if (filtroMagnitud && Number(evento.magnitud) < Number(filtroMagnitud)) {
                return false;
            }
            if (posicionUsuario && radioKm > 0) {
                const distancia = distanciaDeEvento(evento);
                return distancia !== null && distancia <= radioKm;
            }

            return true;
        });
    }

    function actualizarListaEventos(eventos) {
        if (Array.isArray(eventos)) {
            eventosActuales = eventos;
        }

        const listaEventos = document.getElementById('eventos-lista');
        const contadorEventos = document.getElementById('contador-eventos');
        const eventosFiltrados = eventosVisibles();

        listaEventos.innerHTML = eventosFiltrados.map(function (evento, indice) {
            const fechaTexto = formatearFechaLocal(evento.fecha_hora);
            const esNuevo = resaltadoNuevoActivo && indice === 0;
            const intensidadPercibida = evento.intensidadPercibida ?? '';
            const intensidadInstrumental = evento.intensidadInstrumental ?? '';
            const aceleracion = evento.aceleracion ?? '';
            const distancia = distanciaDeEvento(evento);
            const fuente = evento.fuente || 'SGC';
            const textoPercibida = intensidadPercibida !== '' && Number(intensidadPercibida) > 0 ? escaparHtml(intensidadPercibida) : escaparHtml(tr('noData'));
            const textoInstrumental = intensidadInstrumental !== '' && Number(intensidadInstrumental) > 0 ? escaparHtml(intensidadInstrumental) : escaparHtml(tr('noData'));
            const textoAceleracion = aceleracion !== '' && aceleracion !== null ? escaparHtml(aceleracion) : escaparHtml(tr('noData'));
            const textoDistancia = distancia === null
                ? tr('activateLocation')
                : tr('kmFromYou', distancia.toFixed(1));
            const textoSentido = Number(evento.sentido) > 0
                ? `<span>${escaparHtml(tr('feltBy', evento.sentido))}</span>`
                : '';

            return `
                <article class="evento-item border-bottom pb-3 mb-3${esNuevo ? ' evento-nuevo' : ''}" tabindex="0" role="link"
                         data-id="${escaparHtml(evento.id)}"
                         data-fecha="${fechaLocalTimestamp(evento.fecha_hora) / 1000}"
                         data-magnitud="${escaparHtml(evento.magnitud)}"
                         data-profundidad="${escaparHtml(evento.profundidad)}"
                         data-lat="${escaparHtml(evento.lat)}"
                         data-lng="${escaparHtml(evento.lng)}"
                         data-fuente="${escaparHtml(fuente)}"
                         data-distancia="${distancia === null ? '' : escaparHtml(distancia.toFixed(1))}"
                         data-ubicacion="${escaparHtml(String(evento.ubicacion).toLowerCase())}"
                         data-municipios="${escaparHtml(String(evento.municipios || evento.ubicacion).toLowerCase())}"
                         data-cdi="${escaparHtml(intensidadPercibida)}"
                         data-mmi="${escaparHtml(intensidadInstrumental)}"
                         data-pga="${escaparHtml(aceleracion)}">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="badge rounded-pill bg-dark-subtle text-dark">M ${escaparHtml(evento.magnitud)}</span>
                        <span class="small text-secondary">${escaparHtml(tr('sourceLabel', fuente))}</span>
                    </div>
                    <h2 class="h6 mb-1">${escaparHtml(evento.ubicacion)}</h2>
                    ${Number(evento.citizen_reports_count) > 0 ? `<p class="small text-success mb-1">${escaparHtml(tr('reportedByUsers', Number(evento.citizen_reports_count)))}</p>` : ''}
                    <p class="small text-secondary mb-2">${escaparHtml(fechaTexto)}</p>
                    <p class="distancia-evento small text-secondary mb-2">${escaparHtml(textoDistancia)}</p>
                    <div class="d-flex gap-3 small text-secondary">
                        <span>${escaparHtml(tr('depthKm', evento.profundidad))}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 small text-secondary">
                        <span>${tr('feltCdi', textoPercibida)}</span>
                        <span>${tr('instrumentalMmi', textoInstrumental)}</span>
                        <span>${tr('pga', textoAceleracion)}</span>
                        ${textoSentido}
                    </div>
                </article>`;
        }).join('');

        if (eventosFiltrados.length === 0) {
            listaEventos.innerHTML = `<p class="small text-secondary mb-0">${escaparHtml(tr('noEventsFiltered'))}</p>`;
        }

        contadorEventos.textContent = eventosFiltrados.length;
        const contadorEventosMovil = document.getElementById('mobile-contador-eventos');
        if (contadorEventosMovil) {
            contadorEventosMovil.textContent = eventosFiltrados.length;
        }
        const resumenFiltros = document.getElementById('filtros-resumen');
        if (resumenFiltros) {
            const resumen = [String(eventosFiltrados.length)];
            if (filtroFuente) {
                resumen.push(filtroFuente);
            }
            if (filtroMagnitud) {
                resumen.push(`M${filtroMagnitud}+`);
            }
            if (posicionUsuario && radioKm > 0) {
                resumen.push(`${radioKm.toLocaleString(T.locale)} km`);
            }
            resumenFiltros.textContent = resumen.join(' · ');
        }
        ordenarEventos();
        actualizarDistanciaListado();

        if (!replayTimer) {
            pintarMarcadoresListado(eventosFiltrados, false);
        }
    }

    let selectorOrden;
    let filtroMunicipio;
    let listaEventos;

    function valorNumerico(elemento, atributo) {
        const valor = Number(elemento.dataset[atributo]);
        return Number.isFinite(valor) && elemento.dataset[atributo] !== '' ? valor : null;
    }

    function compararNumeros(a, b, atributo, direccion) {
        const valorA = valorNumerico(a, atributo);
        const valorB = valorNumerico(b, atributo);
        if (valorA === null && valorB === null) return 0;
        if (valorA === null) return 1;
        if (valorB === null) return -1;
        return (valorB - valorA) * direccion;
    }

    function ordenarEventos() {
        if (!listaEventos || !selectorOrden) {
            return;
        }

        const eventos = Array.from(listaEventos.querySelectorAll('.evento-item'));
        const orden = selectorOrden.value;

        eventos.sort(function (a, b) {
            if (orden === 'fecha-asc') return Number(a.dataset.fecha) - Number(b.dataset.fecha);
            if (orden === 'magnitud-desc') {
                return Number(b.dataset.magnitud) - Number(a.dataset.magnitud);
            }
            if (orden === 'magnitud-asc') return Number(a.dataset.magnitud) - Number(b.dataset.magnitud);
            if (orden === 'cdi-desc') return compararNumeros(a, b, 'cdi', 1);
            if (orden === 'mmi-desc') return compararNumeros(a, b, 'mmi', 1);
            if (orden === 'pga-desc') return compararNumeros(a, b, 'pga', 1);
            if (orden === 'pga-exists') return (a.dataset.pga === '' ? 1 : 0) - (b.dataset.pga === '' ? 1 : 0);
            if (orden === 'distancia-asc') return compararNumeros(a, b, 'distancia', 1);
            return Number(b.dataset.fecha) - Number(a.dataset.fecha);
        });

        eventos.forEach(function (evento) {
            listaEventos.appendChild(evento);
        });

        eventos.forEach(function (evento, indice) {
            evento.classList.toggle('evento-nuevo', resaltadoNuevoActivo && indice === 0);
        });
    }

    let contextoAudio;
    let alertaSismoPendiente = false;

    function desbloquearAudio() {
        if (!('AudioContext' in window || 'webkitAudioContext' in window)) {
            return;
        }
        const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
        contextoAudio = contextoAudio || new AudioContextConstructor();
        if (contextoAudio.state === 'suspended') {
            contextoAudio.resume().then(function () {
                if (alertaSismoPendiente) {
                    alertaSismoPendiente = false;
                    reproducirAlertaSismo();
                }
            }).catch(function () { /* El navegador puede bloquear el audio hasta una interacción. */ });
        }
    }

    function reproducirAlertaSismo() {
        desbloquearAudio();
        if (!contextoAudio || contextoAudio.state !== 'running') {
            alertaSismoPendiente = true;
            return;
        }

        const ahora = contextoAudio.currentTime;
        [660, 880, 1040].forEach(function (frecuencia, indice) {
            const oscilador = contextoAudio.createOscillator();
            const ganancia = contextoAudio.createGain();
            oscilador.type = 'sine';
            oscilador.frequency.value = frecuencia;
            ganancia.gain.setValueAtTime(0.0001, ahora + (indice * 0.16));
            ganancia.gain.exponentialRampToValueAtTime(0.24, ahora + (indice * 0.16) + 0.02);
            ganancia.gain.exponentialRampToValueAtTime(0.0001, ahora + (indice * 0.16) + 0.14);
            oscilador.connect(ganancia);
            ganancia.connect(contextoAudio.destination);
            oscilador.start(ahora + (indice * 0.16));
            oscilador.stop(ahora + (indice * 0.16) + 0.15);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        inicializarMapaListado('mapa', SISMOS, 'distancia-usuario');

        const mapInfo = document.getElementById('map-info');
        const mapInfoToggle = document.getElementById('map-info-toggle');
        const mapInfoContent = document.getElementById('map-info-content');
        if (mapInfo && mapInfoToggle && mapInfoContent) {
            mapInfoToggle.addEventListener('click', function () {
                const abierto = mapInfo.classList.toggle('is-open');
                mapInfoContent.hidden = !abierto;
                mapInfoToggle.setAttribute('aria-expanded', String(abierto));
                mapInfoToggle.setAttribute('aria-label', abierto ? T.hideMapInfo : T.showMapInfo);
            });
        }

        document.addEventListener('pointerdown', desbloquearAudio, { once: true });
        document.addEventListener('keydown', desbloquearAudio, { once: true });

        const eventosSidebar = document.querySelector('.event-sidebar');
        const mobileEventsToggle = document.getElementById('mobile-events-toggle');
        const mobileEventsClose = document.getElementById('mobile-events-close');
        if (eventosSidebar && mobileEventsToggle && mobileEventsClose) {
            function cerrarListadoMovil() {
                eventosSidebar.classList.remove('details-open');
                mobileEventsToggle.setAttribute('aria-expanded', 'false');
            }

            mobileEventsToggle.addEventListener('click', function () {
                eventosSidebar.classList.add('details-open');
                mobileEventsToggle.setAttribute('aria-expanded', 'true');
            });
            mobileEventsClose.addEventListener('click', cerrarListadoMovil);
        }

        const urlSincronizacion = SIGRA_MAP_URLS.sync;
        const urlDetalle = SIGRA_MAP_URLS.detail;
        const avisoNuevo = document.getElementById('sismo-aviso');
        const datosActualizados = document.getElementById('datos-actualizados');
        const setSyncStatus = function (message, warning) {
            const status = document.getElementById('catalog-sync-status');
            if (!status) return;
            status.textContent = message;
            status.classList.toggle('text-warning', warning);
            status.classList.toggle('text-secondary', !warning);
        };
        const sincronizar = function () {
            fetch(urlSincronizacion, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (resultado) {
                    if (resultado.ok && Array.isArray(resultado.eventos)) {
                        eventosActuales = resultado.eventos;
                        const errors = Object.keys(resultado.errors || {});
                        const lastSuccess = Number(resultado.last_success_at) || 0;
                        const statusMessage = errors.length ? tr('catalogDegraded')
                            : lastSuccess > 0 ? tr('catalogUpdated', new Date(lastSuccess * 1000).toLocaleString(T.locale))
                            : tr('catalogUnknown');
                        setSyncStatus(statusMessage, errors.length > 0 || lastSuccess === 0);
                        const eventosNuevos = resultado.eventos.filter(function (evento) {
                            return !idsConocidos.has(String(evento.id));
                        });
                        eventosNuevos.forEach(function (evento) {
                            idsConocidos.add(String(evento.id));
                        });

                        if (eventosNuevos.length > 0) {
                            const ultimoSismo = eventosNuevos.reduce(function (ultimo, evento) {
                                return fechaLocalTimestamp(evento.fecha_hora) > fechaLocalTimestamp(ultimo.fecha_hora)
                                    ? evento
                                    : ultimo;
                            });
                            idsNuevos = new Set([String(ultimoSismo.id)]);
                        }

                        if (eventosNuevos.length > 0) {
                            const mensaje = eventosNuevos.length === 1
                                ? tr('newQuake')
                                : tr('newQuakes', eventosNuevos.length);
                            temporizadorAvisoNuevo = mostrarAviso(avisoNuevo, mensaje, temporizadorAvisoNuevo);
                            reproducirAlertaSismo();
                        }

                        actualizarListaEventos(resultado.eventos);
                        actualizarEstadoRadio();
                        if (resultado.synced) {
                            temporizadorDatosActualizados = mostrarAviso(
                                datosActualizados,
                                tr('updatedToast'),
                                temporizadorDatosActualizados
                            );
                        }
                    } else {
                        setSyncStatus(tr('catalogUnavailable'), true);
                    }
                })
                .catch(function () { setSyncStatus(tr('catalogUnavailable'), true); });
        };

        sincronizar();
        window.setInterval(sincronizar, 15000);
        iniciarTemporizadorResaltado();

            selectorOrden = document.getElementById('orden-eventos');
            filtroMunicipio = document.getElementById('filtro-municipio');
            listaEventos = document.getElementById('eventos-lista');

            selectorOrden.addEventListener('change', ordenarEventos);
            filtroMunicipio.addEventListener('input', function () {
                actualizarListaEventos(eventosActuales);
            });

            selectorFuente = document.getElementById('filtro-fuente');
            selectorMagnitud = document.getElementById('filtro-magnitud');
            selectorRadio = document.getElementById('radio-filtro');
            botonUbicacion = document.getElementById('btn-mi-ubicacion');
            estadoUbicacion = document.getElementById('estado-ubicacion');

            const panelFiltros = document.getElementById('filtros-panel');
            if (panelFiltros && window.matchMedia('(max-width: 850px)').matches) {
                panelFiltros.open = false;
            }

            // Firefox y otros navegadores restauran los <select> al recargar.
            // Sincronizamos el estado real del DOM con las variables del filtro.
            if (selectorRadio) {
                selectorRadio.disabled = false;
                selectorRadio.value = String(radioKm);
                const valorRadio = Number(selectorRadio.value);
                if (Number.isFinite(valorRadio)) {
                    radioKm = valorRadio;
                }
            }
            filtroFuente = selectorFuente ? selectorFuente.value : '';
            filtroMagnitud = selectorMagnitud ? selectorMagnitud.value : '';

            function actualizarEstadoRadio() {
                if (!estadoUbicacion) {
                    return;
                }
                if (ubicacionPendiente) {
                    estadoUbicacion.textContent = T.gettingLocation;
                    return;
                }
                if (!posicionUsuario) {
                    estadoUbicacion.textContent = T.activateLocation;
                    return;
                }

                const total = eventosVisibles().length;
                const fuenteTexto = filtroFuente ? tr('sourceFrom', filtroFuente) : '';
                estadoUbicacion.textContent = radioKm > 0
                    ? tr('radiusStatus', total, fuenteTexto, radioKm.toLocaleString(T.locale))
                    : tr('locationReady', total, fuenteTexto);
            }

            function refrescarVistaFiltros() {
                if (posicionUsuario && radioKm > 0) {
                    dibujarRadioListado(posicionUsuario, radioKm, true);
                } else {
                    if (posicionUsuario) {
                        dibujarRadioListado(posicionUsuario, 0, false);
                    }
                    ajustarVistaListado(eventosVisibles());
                }
            }

            function activarUbicacion(discreto) {
                if (ubicacionPendiente) return;
                if (!navigator.geolocation && typeof fetch !== 'function') {
                    if (estadoUbicacion && discreto !== true) {
                        estadoUbicacion.textContent = T.geoNotAllowed;
                    }
                    return;
                }

                ubicacionPendiente = true;
                if (botonUbicacion) {
                    botonUbicacion.disabled = true;
                }
                if (estadoUbicacion) {
                    estadoUbicacion.textContent = T.gettingLocation;
                }

                obtenerUbicacionUsuario()
                    .then(function (pos) {
                        ubicacionPendiente = false;
                        posicionUsuario = {
                            lat: Number(pos.coords.latitude),
                            lng: Number(pos.coords.longitude)
                        };

                        fijarUbicacionListado(posicionUsuario.lat, posicionUsuario.lng, pos.coords.accuracy);

                        if (selectorRadio) {
                            selectorRadio.disabled = false;
                        }
                        if (radioKm === 0 && !radioElegido) {
                            radioKm = 500;
                            if (selectorRadio) {
                                selectorRadio.value = '500';
                            }
                            guardarRadio(radioKm);
                        }

                        actualizarListaEventos();
                        refrescarVistaFiltros();
                        actualizarEstadoRadio();

                        if (botonUbicacion) {
                            botonUbicacion.querySelector('span').textContent = T.updateLocation;
                            botonUbicacion.disabled = false;
                        }
                    })
                    .catch(function () {
                        ubicacionPendiente = false;
                        if (estadoUbicacion) {
                            estadoUbicacion.textContent = T.locationError;
                        }
                        if (botonUbicacion) {
                            botonUbicacion.disabled = false;
                        }
                    });
            }

            if (selectorFuente) {
                selectorFuente.addEventListener('change', function () {
                    filtroFuente = selectorFuente.value;
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (selectorMagnitud) {
                selectorMagnitud.addEventListener('change', function () {
                    filtroMagnitud = selectorMagnitud.value;
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (selectorRadio) {
                selectorRadio.addEventListener('change', function () {
                    radioKm = Number(selectorRadio.value) || 0;
                    guardarRadio(radioKm);
                    if (radioKm > 0 && !posicionUsuario) {
                        activarUbicacion(false);
                    }
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (botonUbicacion) {
                botonUbicacion.addEventListener('click', function () { activarUbicacion(false); });
            }

            const botonLimpiar = document.getElementById('btn-limpiar-filtros');
            if (botonLimpiar) {
                botonLimpiar.addEventListener('click', function () {
                    if (filtroMunicipio) {
                        filtroMunicipio.value = '';
                    }
                    if (selectorFuente) {
                        selectorFuente.value = '';
                    }
                    if (selectorMagnitud) {
                        selectorMagnitud.value = '';
                    }
                    filtroFuente = '';
                    filtroMagnitud = '';
                    radioKm = 0;
                    if (selectorRadio) {
                        selectorRadio.value = '0';
                    }
                    guardarRadio(0);
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            // Si el usuario ya concedió geolocalización, activamos el filtro
            // sin volver a mostrar un diálogo (el permiso sigue siendo suyo).
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' })
                    .then(function (permiso) {
                        if (permiso.state === 'granted') {
                            activarUbicacion(true);
                        }
                    })
                    .catch(function () { /* El navegador puede no soportar esta consulta. */ });
            }

            actualizarListaEventos();
            actualizarEstadoRadio();

            const replayBoton = document.getElementById('replay-actividad');
            const replayEstado = document.getElementById('replay-estado');

            function detenerReplay() {
                if (!replayTimer) {
                    return;
                }
                window.clearInterval(replayTimer);
                replayTimer = null;
                if (replayBoton) replayBoton.textContent = T.replay;
                if (replayEstado) replayEstado.textContent = '';
            }

            if (replayBoton) {
                replayBoton.addEventListener('click', function () {
                    if (replayTimer) {
                        detenerReplay();
                        pintarMarcadoresListado(eventosVisibles(), false);
                        return;
                    }

                    const ordenados = eventosVisibles().slice().sort(function (a, b) {
                        return fechaLocalTimestamp(a.fecha_hora) - fechaLocalTimestamp(b.fecha_hora);
                    });
                    if (!ordenados.length) {
                        return;
                    }

                    let indice = 0;
                    const paso = Math.max(1, Math.ceil(ordenados.length / 80));
                    replayBoton.textContent = T.replayStop;
                    pintarMarcadoresListado([], false);

                    replayTimer = window.setInterval(function () {
                        indice += paso;
                        const lote = ordenados.slice(0, Math.min(indice, ordenados.length));
                        pintarMarcadoresListado(lote, false);
                        if (replayEstado) {
                            replayEstado.textContent = Math.min(indice, ordenados.length) + '/' + ordenados.length;
                        }
                        if (indice >= ordenados.length) {
                            window.clearInterval(replayTimer);
                            replayTimer = null;
                            replayBoton.textContent = T.replayAgain;
                            if (replayEstado) {
                                replayEstado.textContent = T.replayDone;
                            }
                        }
                    }, 150);
                });
            }

            listaEventos.addEventListener('click', function (evento) {
                const tarjeta = evento.target.closest('.evento-item[data-id]');
                if (tarjeta) {
                    window.location.href = urlDetalle + '/' + encodeURIComponent(tarjeta.dataset.id);
                }
            });

            listaEventos.addEventListener('keydown', function (evento) {
                if (evento.key !== 'Enter' && evento.key !== ' ') {
                    return;
                }

                const tarjeta = evento.target.closest('.evento-item[data-id]');
                if (tarjeta) {
                    evento.preventDefault();
                    window.location.href = urlDetalle + '/' + encodeURIComponent(tarjeta.dataset.id);
                }
            });
    });

})();
