(function () {
    window.SM_INV_DEBUG = window.SM_INV_DEBUG ?? true;

    const DBG_PREFIX = "[SM_INV]";
    const dbg = (...args) => window.SM_INV_DEBUG && console.log(DBG_PREFIX, ...args);
    const dbgWarn = (...args) => window.SM_INV_DEBUG && console.warn(DBG_PREFIX, ...args);
    const dbgErr = (...args) => console.error(DBG_PREFIX, ...args);

    let cfg = null;

    function closest(el, root, predicate) {
        while (el && el !== root) {
            if (predicate(el)) return el;
            el = el.parentNode;
        }
        return null;
    }

    function extractFirstNumber(str) {
        if (!str) return null;
        const m = String(str).match(/(\d+)/);
        return m ? parseInt(m[1], 10) : null;
    }

    function extractFloorNumber(str) {
        if (!str) return null;

        const m = String(str).match(/^floor-(-?\d+)$/);
        return m ? parseInt(m[1], 10) : null;
    }

    function ready(fn) {
        if (document.readyState !== "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
    }

    // ===== URL <-> state (Opcja 1) =====

    function parseUrlState() {
        const path = window.location.pathname.replace(/\/+$/, "");
        const parts = path.split("/").filter(Boolean);

        const state = {
            investmentId: null,   // np. 42 (dla /inwestycje/42/...)
            building: null,       // np. b3
            floor: null,          // np. b3-2
            room: null,           // np. 6 (z #room-6)
        };

        const invIdx = parts.indexOf("inwestycje");
        if (invIdx !== -1) {
            const maybeId = parts[invIdx + 1] || null;
            const idNum = maybeId ? parseInt(maybeId, 10) : NaN;
            state.investmentId = Number.isFinite(idNum) ? idNum : null;
        }

        const bIdx = parts.indexOf("budynki");
        if (bIdx !== -1) state.building = parts[bIdx + 1] || null;

        const fIdx = parts.indexOf("pietra");
        if (fIdx !== -1) state.floor = parts[fIdx + 1] || null;

        if (window.location.hash) {
            const h = window.location.hash.trim();
            if (h.startsWith("#room-")) state.room = h.replace("#room-", "");
        }

        return state;
    }

    function normalizeInvestmentSlug(cfg, nav) {
        let slug =
            cfg?.investmentSlug ||
            nav?.dataset?.invSlug ||
            parseUrlState().investment ||
            "";

        slug = String(slug).trim();
        return slug !== "" ? slug : null;
    }

    function ensureNoDoubleSlashes(url) {
        // nie ruszamy protokołu: https://
        return url.replace(/([^:]\/)\/+/g, "$1");
    }

    function buildPath({ buildingSlug, floorSlug }) {
        const st = parseUrlState();
        const invId = cfg?.investmentId || st.investmentId;

        if (!invId) return window.location.pathname;

        let p = `/inwestycje/${invId}/`;

        if (buildingSlug) {
            p += `budynki/${buildingSlug}/`;
        }

        if (floorSlug) {
            p += `pietra/${floorSlug}/`;
        }

        return ensureNoDoubleSlashes(p);
    }

    function setUrlState({ buildingSlug, floorSlug, roomId }, mode = "push") {
        const path = buildPath({ buildingSlug, floorSlug });
        const hash = roomId ? `#room-${roomId}` : "";
        const url = ensureNoDoubleSlashes(path + hash);

        try {
            if (mode === "replace") history.replaceState({}, "", url);
            else history.pushState({}, "", url);
        } catch (e) {
            dbgWarn("history.*State failed, fallback to location (may scroll)", e);
            location.href = url;
        }
    }

    function scrollToSvgSectionOnce() {
        const anchor =
            document.getElementById("sm-inv-root") ||
            document.getElementById("sm-inv-nav") ||
            null;

        if (!anchor) return;
        try {
            anchor.scrollIntoView({ behavior: "smooth", block: "start" });
        } catch { }
    }

    // function focusRoom(roomId, { scroll = false } = {}) {
    //     if (!roomId) return;
    //     const svg = document.querySelector("#sm-inv-svg svg");
    //     if (!svg) return;

    //     const el = svg.querySelector(`#room-${CSS.escape(String(roomId))}`);
    //     if (!el) return;

    //     el.classList.add("sm-room-active");
    //     setTimeout(() => el.classList.remove("sm-room-active"), 1400);

    //     if (scroll) {
    //         try {
    //             el.scrollIntoView({ behavior: "smooth", block: "center", inline: "center" });
    //         } catch { }
    //     }
    // }

    ready(function () {
        const nav = document.getElementById("sm-inv-nav");
        const svgWrap = document.getElementById("sm-inv-svg");
        const titleEl = document.querySelector(".sm-inv-title");
        const backBtn = document.getElementById("sm-inv-back");
        const legend = document.querySelector(".legend-map");

        if (!nav || !svgWrap || !window.SM_INV) {
            dbgWarn("Missing required DOM elements or SM_INV config");
            return;
        }

        cfg = window.SM_INV;
        let stateStack = [];
        let currentFlats = [];

        // 🔒 blokada akcji (koniec z lagami i stackowaniem)
        let isTransitioning = false;

        // Flagi żeby nie robić pętli (popstate -> REST -> pushState -> popstate...)
        let isRestoringFromPopstate = false;
        let isInitialRestore = false;

        // ważne: slug bierzemy z PHP jeśli jest, inaczej z URL
        const investmentSlug = normalizeInvestmentSlug(cfg, nav);

        // ✅ Root URL inwestycji (żeby back wracał do /inwestycja/{slug}/{id}/)
        const baseInvestmentUrl =
            cfg?.investmentId && investmentSlug
                ? ensureNoDoubleSlashes(`/inwestycja/${investmentSlug}/${cfg.investmentId}/`)
                : null;

        dbg("Resolved investmentSlug:", investmentSlug, "investmentId:", cfg?.investmentId ?? null, "URL state:", parseUrlState());

        /* =========================
           UI helpers
        ========================= */

        function updateBackButton() {
            if (!backBtn) return;
            backBtn.style.display = stateStack.length > 0 ? "inline-block" : "none";
        }

        function updateLegendVisibility() {
            if (!legend) return;

            if (nav.dataset.step === "floor") {
                legend.style.opacity = "1";
                legend.style.height = "100%";
                legend.style.visibility = "visible";
                legend.style.overflow = "visible";
            } else {
                legend.style.opacity = "0";
                legend.style.height = "0";
                legend.style.visibility = "hidden";
                legend.style.overflow = "hidden";
            }
        }

        function pushInternalState() {
            stateStack.push({
                step: nav.dataset.step,
                objectId: nav.dataset.objectId || "",
                buildingSvgNo: nav.dataset.buildingSvgNo || "",
                floorNo: nav.dataset.floorNo || "",
                html: svgWrap.innerHTML,
                title: titleEl?.textContent || "",
            });
            updateBackButton();
        }

        function setActiveFloor(floorNo) {
            const wrap = document.getElementById("floor-nav-wrapper");
            if (!wrap) return;

            wrap.querySelectorAll(".floor-item").forEach((btn) => {
                btn.classList.toggle("active", Number(btn.dataset.floor) === Number(floorNo));
            });
        }

        function applyFlatStatuses(flats = []) {
            const svg = svgWrap.querySelector("svg");
            if (!svg) return;

            // czyść poprzednie statusy
            svg.querySelectorAll(".sm-flat").forEach((el) => {
                el.classList.remove("available", "reserved", "sold");
            });

            flats.forEach((f) => {
                const key = String(f.id_svg || "").trim();
                if (!key) return;

                const el = svg.querySelector(`#room-${CSS.escape(key)}`);
                if (!el) return;

                el.classList.add("sm-flat");

                const st = String(f.status || "available").toLowerCase();
                if (st === "sold" || st === "reserved" || st === "available") el.classList.add(st);
                else el.classList.add("available");

                el.dataset.flatId = String(f.id || "");
            });
        }

        function renderFloorNav(floors = []) {
            const wrap = document.getElementById("floor-nav-wrapper");
            if (!wrap) return;

            wrap.innerHTML = "";

            const sorted = [...floors].sort((a, b) => {
                const an = Number(a.floor_no ?? 0);
                const bn = Number(b.floor_no ?? 0);
                return an - bn;
            });

            sorted.forEach((f) => {
                const no = Number(f.floor_no ?? 0);

                const btn = document.createElement("button");
                btn.className = "floor-item";
                btn.dataset.floor = String(no);
                btn.textContent = String(no);

                btn.addEventListener("click", () => {
                    if (isTransitioning) return;

                    dbg("FLOOR NAV CLICK:", no);
                    setActiveFloor(no);

                    // zmiana piętra w obrębie budynku
                    goObjectToFloor({ id: `floor-${no}`, __fromNav: true }, { urlMode: "push" });
                });

                wrap.appendChild(btn);
            });
        }

        /* =========================
           AJAX
        ========================= */

        async function postStep(payload) {
            const form = new FormData();
            form.append("action", "sm_inv_step");
            form.append("nonce", cfg.nonce);

            Object.entries(payload).forEach(([k, v]) => form.append(k, v));

            const res = await fetch(cfg.ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: form,
            });

            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch {
                dbgErr("Non-JSON response", text);
                return null;
            }
        }

        /* =========================
           Animowana podmiana SVG
        ========================= */

        function replaceSvgWithAnimation(newHtml, direction = "forward", onAfter) {
            if (isTransitioning) return;
            isTransitioning = true;

            const el = svgWrap;

            el.classList.add("animate__animated", "animate__fadeOut", "animate__faster");
            el.style.setProperty("--animate-duration", "0.3s");

            el.addEventListener(
                "animationend",
                () => {
                    el.innerHTML = newHtml;
                    el.classList.remove("animate__fadeOut", "animate__faster");
                    el.style.removeProperty("--animate-duration");

                    el.dataset.animation = "fadeIn";
                    el.dataset.animationInstant = "true";

                    if (window.Animations?.animateElement) {
                        window.Animations.animateElement(el);
                    }

                    if (typeof onAfter === "function") onAfter();

                    isTransitioning = false;
                },
                { once: true }
            );
        }

        /* =========================
           URL helpers (slugi)
        ========================= */

        function buildingSlugFromNo(no) {
            return `b${Number(no)}`;
        }

        function floorSlugFromBuildingAndNo(buildingSlug, floorNo) {
            return `${buildingSlug}-${Number(floorNo)}`;
        }

        function extractBuildingNoFromSlug(buildingSlug) {
            return extractFirstNumber(buildingSlug);
        }

        function extractFloorNoFromSlug(floorSlug) {
            if (!floorSlug) return null;
            const nums = String(floorSlug).match(/(\d+)/g);
            if (!nums || !nums.length) return null;
            return parseInt(nums[nums.length - 1], 10);
        }

        function extractBuildingSlugFromFloorSlug(floorSlug) {
            if (!floorSlug) return null;
            const m = String(floorSlug).match(/^(b\d+)/i);
            return m ? m[1].toLowerCase() : null;
        }

        function getCurrentBuildingSlugSafe() {
            // ✅ KLUCZ: budynek slug oparty o ID SVG (np. 3 => b3)
            const svgNo = nav.dataset.buildingSvgNo;
            if (svgNo && String(svgNo).trim() !== "") return buildingSlugFromNo(svgNo);
            return null;
        }

        /* =========================
           Nawigacja (REST + URL)
        ========================= */

        async function goInvestmentToObject(clickedEl, opts = {}) {
            const {
                pushInternal = true,
                urlMode = "push", // "push" | "replace" | null
                fromUrl = false,
            } = opts;

            if (isTransitioning) return;

            const invId = nav.dataset.invId;
            const svgNo = extractFirstNumber(clickedEl.id); // to jest ID SVG budynku

            if (!invId || svgNo === null) {
                dbgWarn("Missing invId or svg number");
                return;
            }

            if (pushInternal && !isRestoringFromPopstate) pushInternalState();

            const json = await postStep({
                inv_id: invId,
                object_id: svgNo, // tu backend oczekuje ID SVG budynku
            });

            if (!json || !json.ok) {
                dbgErr("Object load failed", json);
                return;
            }

            nav.dataset.objectId = String(json.object_id);     // REAL ID budynku (DB)
            nav.dataset.buildingSvgNo = String(svgNo);         // ✅ ID SVG budynku (do URL!)
            nav.dataset.step = "object";

            renderFloorNav(json.floors || []);

            const bSlug = buildingSlugFromNo(svgNo); // ✅ b3

            if (urlMode && !isRestoringFromPopstate) {
                setUrlState({ investmentSlug, buildingSlug: bSlug, floorSlug: null, roomId: null }, urlMode);
            }

            // brak SVG obiektu -> auto wejście w 1 piętro
            const hasObjectSvg = json.svg_html && json.svg_html.trim() !== "";
            if (!hasObjectSvg) {
                const floorsSorted = [...(json.floors || [])].sort(
                    (a, b) => Number(a.floor_no) - Number(b.floor_no)
                );

                const firstFloor = floorsSorted[0]?.floor_no;
                if (firstFloor !== undefined) {
                    await goObjectToFloor(
                        { id: `floor-${firstFloor}`, __auto: true },
                        {
                            pushInternal: false,
                            urlMode: fromUrl ? "replace" : "push",
                            fromUrl,
                            buildingSlug: bSlug,
                        }
                    );
                }
                return;
            }

            updateLegendVisibility();
            await new Promise(resolve => {
                replaceSvgWithAnimation(json.svg_html, "forward", resolve);
            });

            if (fromUrl && isInitialRestore) scrollToSvgSectionOnce();
        }

        async function goObjectToFloor(clickedEl, opts = {}) {
            const {
                pushInternal = true,
                urlMode = "push", // "push" | "replace" | null
                fromUrl = false,
                buildingSlug = null,
                roomId = null,
            } = opts;

            if (isTransitioning) return;

            const invId = nav.dataset.invId;
            const objectId = nav.dataset.objectId; // REAL ID budynku (DB)
            const floorNo = extractFloorNumber(clickedEl.id);

            if (!invId || !objectId || floorNo === null) {
                dbgWarn("Missing invId, objectId or floorNo");
                return;
            }

            const isFloorChange = nav.dataset.step === "floor" && clickedEl.__fromNav === true;

            // push internal state tylko przy wejściu głębiej
            if (
                pushInternal &&
                !isFloorChange &&
                clickedEl.__auto !== true &&
                nav.dataset.step !== "floor" &&
                !isRestoringFromPopstate
            ) {
                pushInternalState();
            }

            const json = await postStep({
                inv_id: invId,
                object_id: objectId, // tu backend oczekuje REAL ID budynku (DB)
                floor_id: floorNo,   // tu backend oczekuje ID SVG piętra (u Ciebie działa po liczbie)
            });

            if (!json || !json.ok) {
                dbgErr("Floor load failed", json);
                return;
            }

            nav.dataset.step = "floor";
            nav.dataset.floorNo = String(floorNo);

            setActiveFloor(floorNo);
            updateLegendVisibility();

            if (urlMode && !isRestoringFromPopstate) {
                // ✅ BUDYNEK slug zawsze z buildingSvgNo (a nie z objectId DB)
                const safeBuildingSlug = (buildingSlug || getCurrentBuildingSlugSafe() || "").toLowerCase();
                const fSlug = floorSlugFromBuildingAndNo(safeBuildingSlug, floorNo);
                setUrlState({ investmentSlug, buildingSlug: safeBuildingSlug, floorSlug: fSlug, roomId: roomId || null }, urlMode);
            }

            replaceSvgWithAnimation(json.svg_html, "forward", () => {
                currentFlats = Array.isArray(json.flats) ? json.flats : [];
                applyFlatStatuses(currentFlats);
            });

            if (fromUrl && isInitialRestore) scrollToSvgSectionOnce();
        }

        function goBackInternal() {
            if (isTransitioning) return;
            if (!stateStack.length) return;

            // 🔥 jeśli nie mamy pełnego snapshotu (po refreshu)
            if (!stateStack.length || !stateStack[stateStack.length - 1]?.html) {
                const rootUrl = `/inwestycje/${cfg?.investmentId || parseUrlState().investmentId}/#sm-inv-root`;
                window.location.href = rootUrl;
                return;
            }

            const prev = stateStack.pop();

            nav.dataset.step = prev.step;
            nav.dataset.objectId = prev.objectId || "";
            nav.dataset.buildingSvgNo = prev.buildingSvgNo || nav.dataset.buildingSvgNo || "";
            nav.dataset.floorNo = prev.floorNo || "";

            if (titleEl && typeof prev.title === "string") {
                titleEl.textContent = prev.title;
            }

            updateLegendVisibility();
            replaceSvgWithAnimation(prev.html, "back");
            updateBackButton();

            if (nav.dataset.step !== "floor") {
                setActiveFloor(null);
            }

            // ✅ Aktualizacja URL po backu
            if (!isRestoringFromPopstate) {
                if (nav.dataset.step === "investment") {
                    const st = parseUrlState();
                    const invId = cfg?.investmentId || st.investmentId;

                    if (invId) {
                        const rootUrl = `/inwestycje/${invId}/`;
                        try {
                            history.pushState({}, "", rootUrl);
                        } catch {
                            location.href = rootUrl;
                        }
                    }
                } else if (nav.dataset.step === "object") {
                    const bSlug = getCurrentBuildingSlugSafe();
                    if (bSlug) {
                        setUrlState({ investmentSlug, buildingSlug: bSlug, floorSlug: null, roomId: null }, "push");
                    }
                } else if (nav.dataset.step === "floor") {
                    const bSlug = getCurrentBuildingSlugSafe();
                    const fNo = nav.dataset.floorNo || "";
                    if (bSlug && fNo !== "") {
                        const fSlug = floorSlugFromBuildingAndNo(bSlug, fNo);
                        setUrlState({ investmentSlug, buildingSlug: bSlug, floorSlug: fSlug, roomId: null }, "push");
                    }
                }
            }
        }

        /* =========================
           Restore from URL (load / refresh / popstate)
        ========================= */

        async function restoreFromUrlState(urlState, { fromPopstate = false } = {}) {
            isRestoringFromPopstate = fromPopstate;

            try {
                // root: /inwestycje/{slug}/ (albo wejście bez deep-link)
                if (!urlState.building && !urlState.floor) {
                    if (!fromPopstate) {
                        // nie nadpisujemy /inwestycja/{slug}/{id}/ na siłę
                        // ale jeśli użytkownik już jest na /inwestycje/{slug}/ to dbamy o porządek
                        if (urlState.mode === "inwestycje") {
                            setUrlState({ investmentSlug, buildingSlug: null, floorSlug: null, roomId: null }, "replace");
                        }
                    }
                    return;
                }

                // budynek: /budynki/b3/
                const bSlug = (urlState.building || extractBuildingSlugFromFloorSlug(urlState.floor) || "").toLowerCase();
                const bNo = extractBuildingNoFromSlug(bSlug); // 3

                if (!bNo) {
                    dbgWarn("Cannot parse building number from URL state:", urlState);
                    return;
                }

                // jeśli jesteśmy już w budynku i to ten sam, nie ładuj ponownie
                const currentSvgNo = Number(nav.dataset.buildingSvgNo || 0);
                const needLoadBuilding = currentSvgNo !== Number(bNo) || nav.dataset.step === "investment";

                if (needLoadBuilding) {
                    await goInvestmentToObject(
                        { id: `building-${bNo}` },
                        { pushInternal: false, urlMode: null, fromUrl: true }
                    );

                    // 🔒 upewnij się, że budynek faktycznie jest ustawiony
                    if (!nav.dataset.objectId || !nav.dataset.buildingSvgNo) {
                        dbgErr("Building not ready after restore, abort floor restore");
                        return;
                    }
                }


                // piętro: /pietra/b3-3/
                if (urlState.floor) {
                    const floorNo = extractFloorNoFromSlug(urlState.floor);
                    if (!floorNo) {
                        dbgWarn("Cannot parse floor number from URL floor:", urlState.floor);
                        return;
                    }

                    await goObjectToFloor(
                        { id: `floor-${floorNo}`, __fromNav: true },
                        {
                            pushInternal: false,
                            urlMode: "replace",
                            fromUrl: true,
                            buildingSlug: bSlug,
                            roomId: urlState.room || null,
                        }
                    );
                } else {
                    // tylko budynek
                    setUrlState({ investmentSlug, buildingSlug: bSlug, floorSlug: null, roomId: null }, "replace");
                }
                // ✅ ODBUDUJ BACK BUTTON PO REFRESHU
                if (!fromPopstate) {
                    stateStack.length = 0;

                    // jeżeli jesteśmy na budynku
                    if (urlState.building && !urlState.floor) {
                        stateStack.push({ step: "investment" });
                    }

                    // jeżeli jesteśmy na piętrze
                    if (urlState.floor) {
                        stateStack.push({ step: "investment" });
                        stateStack.push({ step: "object" });
                    }

                    updateBackButton();
                }
            } finally {
                isRestoringFromPopstate = false;
            }
        }

        /* =========================
           Eventy SVG
        ========================= */

        function onSvgClick(e) {
            if (isTransitioning) return;

            const svg = svgWrap.querySelector("svg");
            if (!svg) return;

            const step = nav.dataset.step || "investment";

            if (step === "investment") {
                const clicked = closest(e.target, svg, (n) => n?.nodeType === 1 && n.classList?.contains("sm-building"));
                if (!clicked) return;

                e.preventDefault();
                goInvestmentToObject(clicked, { urlMode: "push" });
            }

            if (step === "object") {
                const clicked = closest(e.target, svg, (n) => n?.nodeType === 1 && n.classList?.contains("sm-floor"));
                if (!clicked) return;

                e.preventDefault();
                goObjectToFloor(clicked, { urlMode: "push" });
            }

            if (step === "floor") {
                // klik w mieszkanie -> hash
                const clickedRoom = closest(e.target, svg, (n) => {
                    if (!n || n.nodeType !== 1) return false;
                    const id = n.getAttribute?.("id") || "";
                    return String(id).startsWith("room-");
                });

                if (clickedRoom) {
                    const id = clickedRoom.getAttribute("id");
                    const svgId = String(id).replace("room-", "");

                    const flat = currentFlats.find(f => f.id_svg === svgId);

                    if (flat && flat.url) {
                        window.location.href = flat.url;
                    }
                }
            }
        }

        svgWrap.addEventListener("click", onSvgClick);
        backBtn?.addEventListener("click", goBackInternal);

        // Browser back/forward
        window.addEventListener("popstate", async () => {
            const st = parseUrlState();
            dbg("POPSTATE ->", st);
            isInitialRestore = false;
            await restoreFromUrlState(st, { fromPopstate: true });
        });

        updateLegendVisibility();
        updateBackButton();

        dbg("SM Investment navigation initialized");

        // ===== Initial restore on page load (refresh / wejście z linka) =====
        (async function initialRestore() {
            const st = parseUrlState();

            // deep link: ma budynek/pietro/pokoj
            const hasDeepLink = !!(st.building || st.floor || st.room);

            if (!hasDeepLink) {
                // jeśli jesteś na /inwestycja/{slug}/{id}/ => NIE nadpisuj URL
                return;
            }

            isInitialRestore = true;
            await restoreFromUrlState(st, { fromPopstate: false });
            isInitialRestore = false;
        })();
    });
})();
