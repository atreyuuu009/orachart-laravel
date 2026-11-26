/* Digital odontogram (FDI). Permanent teeth are rendered from the 3D GLB model;
   findings still come from /odontogram/{patient}/findings. Primary teeth are not
   present in the 3D model, so they keep a small 2D reference chart. */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

(() => {
  'use strict';

  const cfg = window.ODONTO;
  const $ = (id) => document.getElementById(id);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const PRIMARY_LAYOUT = {
    upperP: [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
    lowerP: [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
  };

  let patientId = null, findings = [], selected = null, editing = null;

  // 3D viewer state
  let renderer = null, scene = null, camera = null, controls = null;
  let raycaster = null, pointer = null, modelRoot = null;
  let upperJaw = null, lowerJaw = null, upperGums = null, lowerGums = null;
  let tongue = null, lowerGumsSmall = null;
  let viewerEl = null, canvasHost = null, tooltipEl = null;
  const toothMeshes = [];
  const toothByNumber = new Map();
  let hovered = null;
  let viewDistance = 20;
  let userMoved = false;
  let camTween = null;

  const api = async (url, method = 'GET', body) => {
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : undefined });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || 'Request failed');
    return data;
  };

  // ------------------------------------------------------------------ 3D setup
  function initViewer() {
    viewerEl = $('odontoViewer');
    canvasHost = $('odontoCanvas');
    tooltipEl = $('odontoTooltip');

    try {
      renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    } catch (err) {
      showViewerError('WebGL is not available in this browser.');
      return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.cursor = 'grab';
    canvasHost.appendChild(renderer.domElement);

    scene = new THREE.Scene();

    camera = new THREE.PerspectiveCamera(35, 1, 0.1, 500);
    camera.position.set(0, 0, 20);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.85;
    controls.zoomSpeed = 0.9;
    controls.minDistance = 5;
    controls.maxDistance = 80;
    controls.target.set(0, 0, 0);
    controls.update();
    controls.addEventListener('start', () => {
      userMoved = true;
      camTween = null;
      document.querySelectorAll('.odonto-toolbar [data-view]').forEach((btn) => btn.classList.remove('active'));
    });

    scene.add(new THREE.HemisphereLight(0xffffff, 0x64748b, 1.6));

    const key = new THREE.DirectionalLight(0xffffff, 2.1);
    key.position.set(6, 10, 9);
    scene.add(key);

    const fill = new THREE.DirectionalLight(0xd7e6ff, 1.0);
    fill.position.set(-8, -4, 6);
    scene.add(fill);

    const rim = new THREE.DirectionalLight(0xffffff, 0.8);
    rim.position.set(0, 6, -8);
    scene.add(rim);

    raycaster = new THREE.Raycaster();
    pointer = new THREE.Vector2();

    resizeViewer();
    new ResizeObserver(() => {
      resizeViewer();
      if (modelRoot && !userMoved) fitCamera();
    }).observe(canvasHost);

    bindViewerEvents();
    loadModel();
    renderer.setAnimationLoop(animate);
  }

  function resizeViewer() {
    if (!renderer || !canvasHost) return;
    const w = canvasHost.clientWidth || 1;
    const h = canvasHost.clientHeight || 1;
    renderer.setSize(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }

  function showViewerError(message) {
    const loading = $('odontoLoading');
    if (!loading) return;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="odonto-loading-inner text-danger">' +
      '<i class="bi bi-exclamation-triangle fs-4"></i>' +
      '<div class="fw-semibold mt-1">3D viewer unavailable</div>' +
      '<div class="small">' + esc(message) + '</div></div>';
  }

  function loadModel() {
    const loader = new GLTFLoader();
    loader.load(cfg.modelUrl, onModelLoaded, onModelProgress, (err) => {
      console.error('Odontogram model failed to load', err);
      showViewerError('The 3D teeth model could not be loaded.');
    });
  }

  function onModelProgress(event) {
    const bar = $('odontoProgress');
    const text = $('odontoProgressText');
    const wrap = $('odontoProgressBar');
    if (!bar) return;
    let pct = 0;
    if (event.total) pct = Math.min(100, Math.round((event.loaded / event.total) * 100));
    bar.style.width = pct + '%';
    if (text) text.textContent = pct ? pct + '%' : Math.round(event.loaded / 1048576) + ' MB';
    if (wrap) wrap.setAttribute('aria-valuenow', String(pct));
  }

  function onModelLoaded(gltf) {
    const model = gltf.scene;
    model.updateMatrixWorld(true);

    // Center and normalise the model so camera framing is predictable.
    const box = new THREE.Box3().setFromObject(model);
    const center = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());
    model.position.sub(center);

    const wrap = new THREE.Group();
    wrap.add(model);
    const maxDim = Math.max(size.x, size.y, size.z) || 1;
    wrap.scale.setScalar(10 / maxDim);
    scene.add(wrap);
    modelRoot = wrap;

    model.traverse((obj) => {
      if (obj.name === 'Upper_Jaw') upperJaw = obj;
      if (obj.name === 'Lower_Jaw') lowerJaw = obj;
      if (obj.name === 'Upper_Gums') upperGums = obj;
      if (obj.name === 'Lower_Gums') lowerGums = obj;
      if (obj.name === 'Tongue') tongue = obj;
      if (obj.name === 'Lower_Gums_Small') lowerGumsSmall = obj;
      if (!obj.isMesh) return;
      const match = /FDI[_-]?(\d{2})/.exec(obj.name) || (obj.parent && /FDI[_-]?(\d{2})/.exec(obj.parent.name));
      if (!match) return;
      const num = Number(match[1]);
      if (num < 11 || num > 48 || toothByNumber.has(num)) return;

      const source = Array.isArray(obj.material) ? obj.material : [obj.material];
      const mats = source.map((m) => m.clone());
      obj.material = Array.isArray(obj.material) ? mats : mats[0];

      obj.userData.fdi = num;
      obj.userData.mats = mats;
      obj.userData.base = mats.map((m) => ({
        color: m.color.clone(),
        emissive: m.emissive ? m.emissive.clone() : new THREE.Color(0x000000),
        emissiveIntensity: m.emissiveIntensity ?? 1,
        opacity: m.opacity,
        transparent: m.transparent,
        depthWrite: m.depthWrite,
      }));

      toothMeshes.push(obj);
      toothByNumber.set(num, obj);
    });

    $('odontoLoading').classList.add('d-none');
    setJawVisibility('both');
    fitCamera();
    applyFindings();
    const frontBtn = document.querySelector('.odonto-toolbar [data-view="front"]');
    if (frontBtn) frontBtn.classList.add('active');
  }

  function fitCamera() {
    if (!modelRoot || !camera || !controls) return;
    const sphere = new THREE.Box3().setFromObject(modelRoot).getBoundingSphere(new THREE.Sphere());
    const vFov = THREE.MathUtils.degToRad(camera.fov);
    const hFov = 2 * Math.atan(Math.tan(vFov / 2) * camera.aspect);
    viewDistance = (sphere.radius / Math.sin(Math.min(vFov, hFov) / 2)) * 1.08;
    controls.minDistance = sphere.radius * 0.75;
    controls.maxDistance = sphere.radius * 5;
    setCamera(new THREE.Vector3(0, 0, viewDistance), true);
  }

  function setCamera(position, immediate = false) {
    if (!camera || !controls) return;
    const to = position.clone();
    const targetTo = new THREE.Vector3(0, 0, 0);
    if (immediate) {
      camera.position.copy(to);
      controls.target.copy(targetTo);
      controls.update();
      camTween = null;
      return;
    }
    camTween = {
      from: camera.position.clone(),
      to,
      targetFrom: controls.target.clone(),
      targetTo,
      t: 0,
    };
  }

  function setJawVisibility(mode) {
    if (upperJaw) upperJaw.visible = mode !== 'lower';
    if (lowerJaw) lowerJaw.visible = mode !== 'upper';
    // In the occlusal views show only the teeth: the gums, tongue and small
    // retromolar pads would otherwise hide the surfaces being inspected.
    if (upperGums) upperGums.visible = mode === 'both';
    if (lowerGums) lowerGums.visible = mode === 'both';
    if (tongue) tongue.visible = mode === 'both';
    if (lowerGumsSmall) lowerGumsSmall.visible = mode === 'both';
  }

  function setView(name) {
    if (!camera || !controls) return;
    userMoved = true;
    hovered = null;
    hideTooltip();
    applyFindings();
    const d = viewDistance || camera.position.distanceTo(controls.target) || 20;
    const views = {
      front: new THREE.Vector3(0, 0, d),
      upper: new THREE.Vector3(0, -d, 0.01),
      lower: new THREE.Vector3(0, d, 0.01),
      left: new THREE.Vector3(d, 0, 0),
      right: new THREE.Vector3(-d, 0, 0),
    };

    if (name === 'reset') {
      userMoved = false;
      setJawVisibility('both');
      fitCamera();
    } else if (views[name]) {
      setJawVisibility(name === 'upper' ? 'upper' : name === 'lower' ? 'lower' : 'both');
      setCamera(views[name]);
    }

    document.querySelectorAll('.odonto-toolbar [data-view]').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.view === name);
    });
  }

  function animate() {
    if (camTween) {
      camTween.t = Math.min(1, camTween.t + 0.09);
      const e = 1 - Math.pow(1 - camTween.t, 3);
      camera.position.lerpVectors(camTween.from, camTween.to, e);
      controls.target.lerpVectors(camTween.targetFrom, camTween.targetTo, e);
      if (camTween.t >= 1) camTween = null;
    }
    controls.update();
    renderer.render(scene, camera);
  }

  // ------------------------------------------------------------- interaction
  function bindViewerEvents() {
    const el = renderer.domElement;
    let down = null;

    el.addEventListener('pointerdown', (e) => { down = { x: e.clientX, y: e.clientY }; });
    el.addEventListener('pointerup', (e) => {
      if (!down) return;
      const moved = Math.hypot(e.clientX - down.x, e.clientY - down.y);
      down = null;
      if (moved > 6) return;
      const hit = pickTooth(e);
      if (hit) select(hit);
    });
    el.addEventListener('pointermove', (e) => {
      if (e.buttons) { setHover(null); hideTooltip(); return; }
      const hit = pickTooth(e);
      setHover(hit);
      if (hit) showTooltip(e, hit); else hideTooltip();
    });
    el.addEventListener('pointerleave', () => { setHover(null); hideTooltip(); });

    document.querySelectorAll('.odonto-toolbar [data-view]').forEach((btn) => {
      btn.addEventListener('click', () => setView(btn.dataset.view));
    });
  }

  function pickTooth(event) {
    if (!renderer || !camera) return null;
    const rect = renderer.domElement.getBoundingClientRect();
    if (!rect.width || !rect.height) return null;
    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(toothMeshes, false);
    for (const hit of hits) {
      if (isVisible(hit.object)) return hit.object.userData.fdi;
    }
    return null;
  }

  function isVisible(obj) {
    for (let o = obj; o; o = o.parent) {
      if (!o.visible) return false;
    }
    return true;
  }

  function setHover(num) {
    if (hovered === num) return;
    hovered = num;
    applyFindings();
    if (renderer) renderer.domElement.style.cursor = num ? 'pointer' : 'grab';
  }

  function showTooltip(event, num) {
    if (!tooltipEl || !viewerEl) return;
    const list = findings.filter((f) => f.tooth === num);
    let html = '<div class="fw-semibold mb-1">Tooth ' + num + '</div>';
    if (list.length) {
      html += list.map((f) => '<div><span class="legend-dot" style="background:' + esc(f.color) + '"></span>' +
        esc(f.label) + (f.surface !== 'whole' ? ' · ' + esc(f.surface) : '') + '</div>').join('');
    } else {
      html += '<div class="text-white-50">No findings recorded</div>';
    }
    tooltipEl.innerHTML = html;
    tooltipEl.classList.add('show');

    const rect = viewerEl.getBoundingClientRect();
    const x = Math.max(8, Math.min(event.clientX - rect.left + 14, rect.width - tooltipEl.offsetWidth - 8));
    const y = Math.max(8, Math.min(event.clientY - rect.top + 14, rect.height - tooltipEl.offsetHeight - 8));
    tooltipEl.style.left = x + 'px';
    tooltipEl.style.top = y + 'px';
  }

  function hideTooltip() {
    if (tooltipEl) tooltipEl.classList.remove('show');
  }

  // --------------------------------------------------------------- tooth paint
  function applyFindings() {
    toothByNumber.forEach((mesh, num) => {
      const list = findings.filter((f) => f.tooth === num);
      const missing = list.some((f) => f.code === 'MISSING' || f.code === 'EXTRACTED');
      const main = list.find((f) => f.surface === 'whole') || list[0];

      mesh.userData.mats.forEach((mat, i) => {
        const base = mesh.userData.base[i];
        mat.color.copy(base.color);
        if (mat.emissive) {
          mat.emissive.copy(base.emissive);
          mat.emissiveIntensity = base.emissiveIntensity;
        }
        mat.opacity = base.opacity;
        mat.transparent = base.transparent;
        mat.depthWrite = base.depthWrite;

        if (missing) {
          mat.color.set(0x98a2b3);
          mat.transparent = true;
          mat.opacity = selected === num ? 0.38 : 0.16;
          mat.depthWrite = false;
        } else if (main && main.color) {
          mat.color.lerp(new THREE.Color(main.color), 0.6);
          if (mat.emissive) {
            mat.emissive.set(main.color);
            mat.emissiveIntensity = 0.16;
          }
        }

        if (selected === num) {
          if (mat.emissive) {
            mat.emissive.set(0x243b8f);
            mat.emissiveIntensity = 0.6;
          }
          mat.color.lerp(new THREE.Color(0x243b8f), 0.18);
        } else if (hovered === num && mat.emissive) {
          mat.emissive.set(0x4f7cf7);
          mat.emissiveIntensity = 0.32;
        }
        mat.needsUpdate = true;
      });
    });
  }

  // ---------------------------------------------------------- primary 2D chart
  function primaryVisible() {
    const wrap = $('primaryWrap');
    return wrap && !wrap.classList.contains('d-none');
  }

  function drawPrimaryRow(el, halves) {
    if (!el) return;
    el.replaceChildren();
    halves.forEach((half, i) => {
      if (i) { const g = document.createElement('div'); g.className = 'tooth-gap'; el.appendChild(g); }
      half.forEach((n) => {
        const list = findings.filter((f) => f.tooth === n);
        const t = document.createElement('div');
        t.className = 'tooth' + (selected === n ? ' selected' : '') + (list.some((f) => f.code === 'MISSING' || f.code === 'EXTRACTED') ? ' missing' : '');
        const whole = list.find((f) => f.surface === 'whole');
        const main = whole || list[0];
        t.innerHTML = '<div>' + n + '</div><div class="crown"' + (main ? ' style="background:' + main.color + '"' : '') + '>' +
          (main ? '<span class="mark">' + esc(main.code.slice(0, 3)) + '</span>' : '') + '</div>' +
          (list.length > 1 ? '<div class="dots">' + list.slice(0, 3).map((f) => '<i style="background:' + f.color + '"></i>').join('') + '</div>' : '');
        t.title = list.map((f) => f.label + (f.surface !== 'whole' ? ' (' + f.surface + ')' : '')).join(', ') || 'No findings';
        t.addEventListener('click', () => select(n));
        el.appendChild(t);
      });
    });
  }

  function drawPrimaryRows() {
    if (!primaryVisible()) return;
    drawPrimaryRow($('rowUpperP'), PRIMARY_LAYOUT.upperP);
    drawPrimaryRow($('rowLowerP'), PRIMARY_LAYOUT.lowerP);
  }

  // --------------------------------------------------------------- page state
  function draw() {
    applyFindings();
    drawPrimaryRows();
    renderPanel();
    updateSelectionUI();
  }

  function select(n) {
    selected = n;
    resetForm();
    draw();
  }

  function updateSelectionUI() {
    const jump = $('toothJump');
    if (jump) {
      const has = selected && jump.querySelector('option[value="' + selected + '"]');
      jump.value = has ? String(selected) : '';
    }
    const label = $('selectedToothLabel');
    if (label) {
      if (!selected) {
        label.textContent = '';
      } else {
        const count = findings.filter((f) => f.tooth === selected).length;
        label.textContent = 'Selected: tooth ' + selected + (count ? ' · ' + count + ' finding' + (count === 1 ? '' : 's') : '');
      }
    }
  }

  function renderPanel() {
    const box = $('toothFindings');
    if (!patientId) { box.textContent = 'Select a patient first.'; return; }
    if (!selected) {
      box.textContent = 'Select a tooth.';
      $('toothTitle').textContent = 'Tooth details';
      if ($('findingForm')) $('findingForm').classList.add('d-none');
      return;
    }
    $('toothTitle').textContent = 'Tooth ' + selected;
    const list = findings.filter((f) => f.tooth === selected);
    box.innerHTML = list.length ? list.map((f) =>
      '<div class="border rounded-3 p-2 mb-2"><div class="d-flex justify-content-between"><strong><span class="legend-dot" style="background:' + f.color + '"></span>' + esc(f.label) + '</strong>' +
      (cfg.canEdit ? '<span><button class="btn btn-link btn-sm p-0 me-2" data-edit="' + f.id + '">Edit</button><button class="btn btn-link btn-sm text-danger p-0" data-del="' + f.id + '">Remove</button></span>' : '') + '</div>' +
      '<div class="small text-secondary">' + (f.surface !== 'whole' ? 'Surface: ' + esc(f.surface) + ' · ' : '') + esc(f.category) + (f.by ? ' · ' + esc(f.by) : '') + '</div>' +
      (f.notes ? '<div class="small mt-1">' + esc(f.notes) + '</div>' : '') + '</div>').join('') : '<span class="text-secondary">No findings recorded — tooth is considered healthy.</span>';
    if ($('findingForm')) { $('findingForm').classList.remove('d-none'); syncSurface(); }
  }

  function syncSurface() {
    const opt = $('condSelect').selectedOptions[0];
    $('surfaceWrap').classList.toggle('d-none', !opt || opt.dataset.applies === 'tooth');
  }

  function resetForm() {
    editing = null;
    if (!$('findingForm')) return;
    $('findingId').value = ''; $('notesInput').value = ''; $('saveFinding').textContent = 'Add finding'; $('cancelEdit').classList.add('d-none');
  }

  async function loadPatient(id) {
    patientId = id || null; selected = null; findings = []; resetForm(); $('historyBtn').disabled = !patientId;
    if (!patientId) { $('patientInfo').textContent = 'Select a patient to load the chart.'; draw(); return; }
    const d = await api(cfg.findingsUrl + '/' + patientId + '/findings');
    findings = d.findings;
    $('patientInfo').innerHTML = '<strong>' + esc(d.patient.name) + '</strong> · ' + esc(d.patient.code) + ' · Age ' + d.patient.age + (d.patient.sex ? ' · ' + esc(d.patient.sex) : '');
    if (d.patient.age < 13) $('showPrimary').checked = true;
    $('primaryWrap').classList.toggle('d-none', !$('showPrimary').checked);
    draw();
  }

  // ------------------------------------------------------------------- events
  $('patientSelect').addEventListener('change', (e) => loadPatient(e.target.value).catch((err) => alert(err.message)));
  $('showPrimary').addEventListener('change', (e) => {
    $('primaryWrap').classList.toggle('d-none', !e.target.checked);
    draw();
  });
  $('toothJump').addEventListener('change', (e) => {
    if (e.target.value) select(Number(e.target.value));
  });

  $('toothFindings').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    try {
      if (ed) {
        const f = findings.find((x) => x.id == ed.dataset.edit); editing = f;
        $('condSelect').value = f.condition_id; $('surfaceSelect').value = f.surface === 'whole' ? 'mesial' : f.surface;
        $('notesInput').value = f.notes || ''; $('saveFinding').textContent = 'Save changes'; $('cancelEdit').classList.remove('d-none'); syncSurface();
      } else if (del && confirm('Remove this finding? It stays in the history log.')) {
        await api(cfg.findingsUrl + '/findings/' + del.dataset.del, 'DELETE');
        findings = findings.filter((x) => x.id != del.dataset.del); draw();
      }
    } catch (err) { alert(err.message); }
  });

  if (cfg.canEdit) {
    $('condSelect').addEventListener('change', syncSurface);
    $('cancelEdit').addEventListener('click', () => { resetForm(); renderPanel(); });
    $('findingForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const applies = $('condSelect').selectedOptions[0].dataset.applies;
      const body = { condition_code_id: $('condSelect').value, surface: applies === 'tooth' ? 'whole' : $('surfaceSelect').value, notes: $('notesInput').value };
      try {
        if (editing) {
          const f = await api(cfg.findingsUrl + '/findings/' + editing.id, 'PUT', body);
          findings = findings.map((x) => (x.id === f.id ? f : x));
        } else {
          const f = await api(cfg.findingsUrl + '/' + patientId + '/findings', 'POST', { ...body, tooth_number: selected });
          findings = findings.filter((x) => x.id !== f.id).concat(f);
        }
        resetForm(); draw();
      } catch (err) { alert(err.message); }
    });
  }

  $('historyBtn').addEventListener('click', async () => {
    const rows = await api(cfg.findingsUrl + '/' + patientId + '/history' + (selected ? '?tooth=' + selected : ''));
    const label = (v) => v ? esc(v.condition) + (v.surface && v.surface !== 'whole' ? ' (' + esc(v.surface) + ')' : '') : '—';
    $('historyBody').innerHTML = rows.length ? '<table class="table table-sm"><thead><tr><th>When</th><th>Tooth</th><th>Action</th><th>Before</th><th>After</th><th>By</th></tr></thead><tbody>' +
      rows.map((h) => '<tr><td class="text-nowrap">' + esc(h.at) + '</td><td>' + h.tooth + '</td><td><span class="badge text-bg-secondary">' + h.action + '</span></td><td>' + label(h.old) + '</td><td>' + label(h.new) + '</td><td>' + esc(h.by) + '</td></tr>').join('') + '</tbody></table>'
      : '<p class="text-secondary">No history yet.</p>';
    bootstrap.Modal.getOrCreateInstance($('historyModal')).show();
  });

  initViewer();
  draw();
  if (cfg.selected) { $('patientSelect').value = cfg.selected; loadPatient(cfg.selected); }
})();
