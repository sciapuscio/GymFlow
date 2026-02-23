// GymFlow — Session Builder Engine
const GFBuilder = (() => {
    let blocks = [];
    let selectedIdx = null;
    let exercises = [];
    let activeMuscleFiler = null;
    let dragSrcIdx = null;

    const BLOCK_DEFAULTS = {
        interval: { name: 'Intervalo', config: { rounds: 3, work: 40, rest: 20 }, exercises: [] },
        tabata: { name: 'Tabata', config: { rounds: 8, work: 20, rest: 10 }, exercises: [] },
        amrap: { name: 'AMRAP', config: { duration: 600 }, exercises: [] },
        emom: { name: 'EMOM', config: { duration: 600 }, exercises: [] },
        fortime: { name: 'For Time', config: { rounds: 3, time_cap: 1800 }, exercises: [] },
        series: { name: 'Series', config: { sets: 3, reps: 10, rest: 60 }, exercises: [] },
        circuit: { name: 'Circuito', config: { rounds: 2, station_time: 40, rest: 20 }, exercises: [] },
        rest: { name: 'Descanso', config: { duration: 120 }, exercises: [] },
        briefing: { name: 'Briefing', config: { duration: 180, title: '', description: '' }, exercises: [] },
    };

    const BLOCK_ICONS = { interval: '⏱️', tabata: '🔥', amrap: '♾️', emom: '⚡', fortime: '🏁', series: '💪', circuit: '🔄', rest: '😴', briefing: '📋' };

    function init(editSession) {
        loadExercises();
        renderBlockPaletteDrag();
        if (editSession?.blocks_json) {
            loadBlocks(editSession.blocks_json);
        } else {
            renderCanvas();
        }
        updateSummary();
    }

    async function loadExercises() {
        try {
            exercises = await GF.get(window.GF_BASE + '/api/exercises.php');
            renderExerciseList(exercises);
        } catch (e) { console.error('Failed to load exercises', e); }
    }

    function renderExerciseList(list) {
        const el = document.getElementById('exercise-list');
        if (!el) return;
        if (!list.length) { el.innerHTML = '<p style="color:var(--gf-text-dim);font-size:13px;padding:12px 0">Sin ejercicios</p>'; return; }
        el.innerHTML = list.map(ex => `
      <div class="exercise-chip" draggable="true" data-ex-id="${ex.id}" data-ex-name="${ex.name}" data-muscle="${ex.muscle_group}"
           ondragstart="GFBuilder.exerciseDragStart(event,${ex.id})" ondblclick="GFBuilder.addExerciseToSelected(${ex.id})">
        <div class="muscle-dot" style="background:${muscleColor(ex.muscle_group)}"></div>
        <span style="flex:1;font-size:12px">${ex.name}</span>
        <span style="font-size:10px;color:var(--gf-text-dim)">${ex.duration_rec}s</span>
      </div>
    `).join('');
    }

    function muscleColor(m) {
        const colors = { chest: '#ef4444', back: '#3b82f6', shoulders: '#8b5cf6', arms: '#ec4899', core: '#f59e0b', legs: '#10b981', glutes: '#f97316', full_body: '#00f5d4', cardio: '#06b6d4' };
        return colors[m] || '#888';
    }

    function filterExercises(q) {
        let list = exercises;
        if (activeMuscleFiler) list = list.filter(e => e.muscle_group === activeMuscleFiler);
        if (q) list = list.filter(e => e.name.toLowerCase().includes(q.toLowerCase()) || (e.name_es || '').toLowerCase().includes(q.toLowerCase()));
        renderExerciseList(list);
    }

    function filterMuscle(btn) {
        const m = btn.dataset.muscle;
        if (activeMuscleFiler === m) { activeMuscleFiler = null; btn.classList.remove('active'); }
        else { activeMuscleFiler = m; document.querySelectorAll('.muscle-chip').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
        filterExercises(document.getElementById('ex-search')?.value || '');
    }

    function renderBlockPaletteDrag() {
        document.querySelectorAll('.block-type-card[draggable]').forEach(card => {
            card.addEventListener('dragstart', e => {
                e.dataTransfer.setData('blockType', card.dataset.type);
                e.dataTransfer.effectAllowed = 'copy';
            });
        });
    }

    function loadBlocks(newBlocks) {
        blocks = JSON.parse(JSON.stringify(newBlocks));
        renderCanvas();
        updateSummary();
    }

    function addBlockByType(type) {
        const def = BLOCK_DEFAULTS[type] || { name: type, config: {}, exercises: [] };
        const block = JSON.parse(JSON.stringify(def));
        block.type = type;
        blocks.push(block);
        renderCanvas();
        selectBlock(blocks.length - 1);
        updateSummary();
    }

    function renderCanvas() {
        const canvas = document.getElementById('blocks-canvas');
        if (!canvas) return;

        if (!blocks.length) {
            canvas.innerHTML = `<div class="drop-zone" id="empty-drop" ondragover="GFBuilder.canvasDragOver(event)" ondrop="GFBuilder.canvasDrop(event,-1)">
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin:0 auto 8px;display:block;color:var(--gf-text-dim)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
        Arrastrá un bloque o hacé click en un tipo para comenzar
      </div>`;
            return;
        }

        canvas.innerHTML = `<div id="drop-before-0" class="drop-indicator" ondragover="GFBuilder.canvasDragOver(event)" ondrop="GFBuilder.canvasDrop(event,0)"></div>` +
            blocks.map((b, i) => renderBlock(b, i)).join('') + '';
    }

    function renderBlock(block, i) {
        const dur = formatDuration(computeBlockDuration(block));
        const exNames = (block.exercises || []).slice(0, 3).map(e => e.name || e).filter(Boolean);
        const moreEx = (block.exercises || []).length > 3 ? `+${(block.exercises.length - 3)} más` : '';
        const selected = i === selectedIdx ? ' selected' : '';
        const icon = BLOCK_ICONS[block.type] || '▪';

        // Music chip (only shown if WOD generator attached music metadata)
        let musicChip = '';
        if (block.music) {
            const m = block.music;
            const isChill = ['briefing', 'rest'].includes(block.type);
            const chipBg = isChill
                ? 'linear-gradient(90deg,#1e3a5f,#0ea5e9)'
                : 'linear-gradient(90deg,#7c1f1f,#ef4444)';
            musicChip = `
  <div style="display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap">
    <span style="display:inline-flex;align-items:center;gap:4px;background:${chipBg};color:#fff;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:600">
      ${m.icon || '🎵'} ${m.genre || ''}
    </span>
    <span style="font-size:10px;color:var(--gf-text-muted)"><strong>${m.artist || ''}</strong>${m.track ? ' — ' + m.track : ''}</span>
  </div>`;
        }

        return `
    <div class="canvas-block${selected}" data-idx="${i}" data-type="${block.type}"
         draggable="true" onclick="GFBuilder.selectBlock(${i})"
         ondragstart="GFBuilder.blockDragStart(event,${i})"
         ondragend="GFBuilder.blockDragEnd(event)"
         ondragover="GFBuilder.canvasDragOver(event)" ondrop="GFBuilder.canvasDrop(event,${i})">
      <div class="canvas-block-header">
        <span class="canvas-block-drag-handle">⠿</span>
        <span class="canvas-block-type-badge" data-type="${block.type}">${icon} ${block.type.toUpperCase()}</span>
        <span class="canvas-block-name">${block.name || 'Bloque'}</span>
        <span class="canvas-block-duration">${dur}</span>
        <div class="canvas-block-actions">
          <button class="btn btn-ghost btn-icon" style="width:28px;height:28px" onclick="event.stopPropagation();GFBuilder.duplicateBlock(${i})" title="Duplicar">⧉</button>
          <button class="btn btn-icon" style="width:28px;height:28px;background:rgba(239,68,68,.15);color:#ef4444;border:none;cursor:pointer;border-radius:6px" onclick="event.stopPropagation();GFBuilder.removeBlock(${i})" title="Eliminar">×</button>
        </div>
      </div>
      ${exNames.length ? `<div class="canvas-block-exercises">${exNames.map(n => `<span class="ex-pill">${n}</span>`).join('')}${moreEx ? `<span class="ex-pill">${moreEx}</span>` : ''}</div>` : ''}
      ${musicChip}
    </div>
    <div class="drop-indicator" data-after="${i}" ondragover="GFBuilder.canvasDragOver(event)" ondrop="GFBuilder.canvasDrop(event,${i + 1})"></div>`;
    }

    function selectBlock(idx) {
        selectedIdx = idx;
        document.querySelectorAll('.canvas-block').forEach((el, i) => {
            el.classList.toggle('selected', i === idx);
        });
        renderProps(blocks[idx], idx);
    }

    function renderProps(block, idx) {
        const el = document.getElementById('props-content');
        if (!el || !block) return;

        const cfg = block.config || {};
        const repTypes = ['amrap', 'emom', 'fortime', 'circuit', 'series'];
        const showRepsInput = repTypes.includes(block.type);
        const exList = (block.exercises || []).map((ex, ei) => `
  <div class="block-exercise-item">
    <span class="handle">⠿</span>
    <span class="ex-name" style="flex:1">${ex.name || ex}</span>
    ${showRepsInput ? `<input type="number" class="form-control"
      style="width:58px;padding:4px 6px;text-align:center;flex-shrink:0;font-size:12px"
      value="${ex.reps ?? 10}" min="1" max="999" placeholder="reps" title="Repeticiones"
      onchange="GFBuilder.updateExReps(${idx},${ei},+this.value)">` : ''}
    <button class="btn btn-icon btn-danger" style="width:26px;height:26px;font-size:12px" onclick="GFBuilder.removeExFromBlock(${idx},${ei})">×</button>
  </div>
`).join('');

        const commonFields = `
      <div class="form-group">
        <label class="form-label">Nombre del Bloque</label>
        <input class="form-control" value="${(block.name || '').replace(/"/g, '&quot;')}" oninput="GFBuilder.updateBlock(${idx},'name',this.value)">
      </div>`;

        let typeFields = '';
        switch (block.type) {
            case 'interval':
                typeFields = `
          <div class="param-row">
            <div class="form-group"><label class="form-label">Rondas</label><input type="number" class="form-control" value="${cfg.rounds || 3}" min="1" oninput="GFBuilder.updateConfig(${idx},'rounds',+this.value)"></div>
            <div class="form-group"><label class="form-label">Work (s)</label><input type="number" class="form-control" value="${cfg.work || 40}" min="5" oninput="GFBuilder.updateConfig(${idx},'work',+this.value)"></div>
            <div class="form-group"><label class="form-label">Rest (s)</label><input type="number" class="form-control" value="${cfg.rest || 20}" min="0" oninput="GFBuilder.updateConfig(${idx},'rest',+this.value)"></div>
          </div>`;
                break;
            case 'tabata':
                typeFields = `
          <div class="param-row">
            <div class="form-group"><label class="form-label">Rondas</label><input type="number" class="form-control" value="${cfg.rounds || 8}" min="1" oninput="GFBuilder.updateConfig(${idx},'rounds',+this.value)"></div>
            <div class="form-group"><label class="form-label">Work (s)</label><input type="number" class="form-control" value="${cfg.work || 20}" oninput="GFBuilder.updateConfig(${idx},'work',+this.value)"></div>
            <div class="form-group"><label class="form-label">Rest (s)</label><input type="number" class="form-control" value="${cfg.rest || 10}" oninput="GFBuilder.updateConfig(${idx},'rest',+this.value)"></div>
          </div>`;
                break;
            case 'amrap': case 'emom':
                typeFields = `<div class="form-group"><label class="form-label">Duración (s)</label><input type="number" class="form-control" value="${cfg.duration || 600}" oninput="GFBuilder.updateConfig(${idx},'duration',+this.value)"></div>`;
                break;
            case 'fortime':
                typeFields = `
          <div class="param-row">
            <div class="form-group"><label class="form-label">Rondas</label><input type="number" class="form-control" value="${cfg.rounds || 3}" min="1" oninput="GFBuilder.updateConfig(${idx},'rounds',+this.value)"></div>
            <div class="form-group"><label class="form-label">Time Cap (s)</label><input type="number" class="form-control" value="${cfg.time_cap || 1800}" oninput="GFBuilder.updateConfig(${idx},'time_cap',+this.value)"></div>
          </div>`;
                break;
            case 'series':
                typeFields = `
          <div class="param-row">
            <div class="form-group"><label class="form-label">Series</label><input type="number" class="form-control" value="${cfg.sets || 3}" min="1" oninput="GFBuilder.updateConfig(${idx},'sets',+this.value)"></div>
            <div class="form-group"><label class="form-label">Reps</label><input type="number" class="form-control" value="${cfg.reps || 10}" min="1" oninput="GFBuilder.updateConfig(${idx},'reps',+this.value)"></div>
            <div class="form-group"><label class="form-label">Descanso (s)</label><input type="number" class="form-control" value="${cfg.rest || 60}" oninput="GFBuilder.updateConfig(${idx},'rest',+this.value)"></div>
          </div>`;
                break;
            case 'circuit':
                typeFields = `
          <div class="param-row">
            <div class="form-group"><label class="form-label">Rondas</label><input type="number" class="form-control" value="${cfg.rounds || 2}" min="1" oninput="GFBuilder.updateConfig(${idx},'rounds',+this.value)"></div>
            <div class="form-group"><label class="form-label">Tiempo/Est. (s)</label><input type="number" class="form-control" value="${cfg.station_time || 40}" oninput="GFBuilder.updateConfig(${idx},'station_time',+this.value)"></div>
            <div class="form-group"><label class="form-label">Descanso (s)</label><input type="number" class="form-control" value="${cfg.rest || 20}" oninput="GFBuilder.updateConfig(${idx},'rest',+this.value)"></div>
          </div>`;
                break;
            case 'rest': case 'briefing':
                typeFields = `<div class="form-group"><label class="form-label">Duración (s)</label><input type="number" class="form-control" value="${cfg.duration || 120}" oninput="GFBuilder.updateConfig(${idx},'duration',+this.value)"></div>`;
                if (block.type === 'briefing') typeFields += `
          <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-control" rows="3" oninput="GFBuilder.updateConfig(${idx},'description',this.value)">${cfg.description || ''}</textarea></div>`;
                break;
        }

        const hasExercises = !['rest', 'briefing'].includes(block.type);
        const spUri = block.spotify_uri || '';
        const spName = block.spotify_name || '';
        const spIntro = block.spotify_intro || 0;
        el.innerHTML = `
      ${commonFields}
      ${typeFields}
      ${hasExercises ? `
        <hr style="border-color:var(--gf-border);margin:16px 0">
        <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-dim);margin-bottom:10px">Ejercicios</div>
        <div class="block-exercise-list">${exList || '<p style="color:var(--gf-text-dim);font-size:12px">Sin ejercicios. Arrastrá desde la lista o hacé doble-click.</p>'}</div>
        <button class="btn btn-secondary btn-sm" style="width:100%;margin-top:10px" onclick="GFBuilder.randomFill(${idx})">🎲 Random Inteligente</button>
      ` : ''}
      ${window.SPOTIFY_CONNECTED ? `
        <hr style="border-color:var(--gf-border);margin:16px 0">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z"/></svg>
          <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-dim)">Spotify</span>
        </div>
        ${spUri ? `
          <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(29,185,84,.1);border:1px solid rgba(29,185,84,.3);border-radius:8px;margin-bottom:8px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z"/></svg>
            <span style="font-size:11px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1DB954">${spName}</span>
            <button class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px;color:rgba(255,255,255,.4)" onclick="spBlockClear(${idx})">✕</button>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <label style="font-size:11px;color:var(--gf-text-dim);white-space:nowrap">⏱ Intro (s)</label>
            <input type="number" min="0" max="60" class="form-control" style="width:70px;font-size:12px;padding:4px 8px" value="${spIntro}" placeholder="0"
              oninput="GFBuilder.setBlockProp(${idx},'spotify_intro',+this.value)" title="Segundos de intro antes de empezar el timer (pantalla PREPARATE)">
            <span style="font-size:10px;color:var(--gf-text-dim)">seg de PREPARATE</span>
          </div>
        ` : ''}
        ${block.music && !spUri ? (() => {
                    const m = block.music;
                    const isChill = ['briefing', 'rest'].includes(block.type);
                    const chipBg = isChill ? 'linear-gradient(90deg,#1e3a5f,#0ea5e9)' : 'linear-gradient(90deg,#7c1f1f,#ef4444)';
                    return `<div style="margin-bottom:10px;padding:8px 10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px">
              <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:6px">✨ Sugerencia del WOD</div>
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                <span style="display:inline-flex;align-items:center;gap:4px;background:${chipBg};color:#fff;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:600">${m.icon || '🎵'} ${m.genre || ''}</span>
                <span style="font-size:11px;color:var(--gf-text-muted);flex:1"><strong>${m.artist || ''}</strong>${m.track ? ' — ' + m.track : ''}</span>
                <button class="btn btn-ghost btn-sm" style="padding:3px 8px;font-size:10px;border:1px solid rgba(29,185,84,.4);color:#1DB954"
                  onclick="document.getElementById('sp-block-search-${idx}').value='${(m.query || '').replace(/'/g, "\\'")}';spBlockSearch(${idx})">🔍 Buscar</button>
              </div>
            </div>`;
                })() : ''}
        <div style="display:flex;gap:6px">
          <input class="form-control" id="sp-block-search-${idx}" placeholder="Buscar canción o playlist..." style="font-size:12px;padding:6px 10px" onkeydown="if(event.key==='Enter')spBlockSearch(${idx})" value="${block.music && !spUri ? (block.music.query || '') : ''}">
          <button class="btn btn-ghost btn-sm" style="padding:6px 8px" onclick="spBlockSearch(${idx})">🔍</button>
        </div>
        <div id="sp-block-results-${idx}" style="margin-top:6px;max-height:140px;overflow-y:auto;display:flex;flex-direction:column;gap:3px"></div>
      ` : ''}
    `;
    }

    function updateBlock(idx, key, val) {
        if (blocks[idx]) { blocks[idx][key] = val; updateCanvas(idx); }
    }

    function updateConfig(idx, key, val) {
        if (blocks[idx]) { blocks[idx].config = { ...(blocks[idx].config || {}), [key]: val }; updateCanvas(idx); }
    }

    function updateCanvas(idx) {
        const card = document.querySelector(`.canvas-block[data-idx="${idx}"]`);
        if (card) {
            const tmp = document.createElement('div');
            tmp.innerHTML = renderBlock(blocks[idx], idx);
            card.replaceWith(tmp.firstElementChild);
        }
        updateSummary();
    }

    function removeBlock(idx) {
        blocks.splice(idx, 1);
        if (selectedIdx >= blocks.length) selectedIdx = null;
        renderCanvas();
        if (selectedIdx !== null) renderProps(blocks[selectedIdx], selectedIdx);
        updateSummary();
    }

    function duplicateBlock(idx) {
        const copy = JSON.parse(JSON.stringify(blocks[idx]));
        copy.name = (copy.name || 'Bloque') + ' (copia)';
        blocks.splice(idx + 1, 0, copy);
        renderCanvas();
        updateSummary();
    }

    function removeExFromBlock(blockIdx, exIdx) {
        if (blocks[blockIdx]?.exercises) {
            blocks[blockIdx].exercises.splice(exIdx, 1);
            renderCanvas();
            renderProps(blocks[blockIdx], blockIdx);
            updateSummary();
        }
    }

    function updateExReps(blockIdx, exIdx, reps) {
        if (!blocks[blockIdx]?.exercises?.[exIdx]) return;
        blocks[blockIdx].exercises[exIdx].reps = Math.max(1, reps || 1);
    }

    function addExerciseToSelected(exId) {
        if (selectedIdx === null) return showToast('Seleccioná un bloque primero', 'info');
        const ex = exercises.find(e => e.id == exId);
        if (!ex) return;
        if (!blocks[selectedIdx].exercises) blocks[selectedIdx].exercises = [];
        blocks[selectedIdx].exercises.push({ id: ex.id, name: ex.name, reps: 10, duration: ex.duration_rec });
        renderCanvas();
        selectBlock(selectedIdx);
        updateSummary();
    }

    async function randomFill(blockIdx) {
        const block = blocks[blockIdx];
        const usedMuscles = blocks.filter((_, i) => i !== blockIdx && i > blockIdx - 2).flatMap(b => (b.exercises || []).map(e => exercises.find(x => x.id === e.id)?.muscle_group)).filter(Boolean);
        try {
            const result = await GF.post(window.GF_BASE + '/api/exercises.php?random=1', { count: 3, exclude_muscle: usedMuscles });
            block.exercises = result.map(e => ({ id: e.id, name: e.name, reps: 10, duration: e.duration_rec }));
            renderCanvas();
            selectBlock(blockIdx);
            showToast('Ejercicios generados', 'success');
        } catch (e) { showToast('Error al generar', 'error'); }
    }

    // Drag handlers
    function blockDragStart(e, idx) {
        dragSrcIdx = idx;
        e.dataTransfer.effectAllowed = 'move';
        e.currentTarget.classList.add('dragging');
    }

    function blockDragEnd(e) {
        e.currentTarget.classList.remove('dragging');
        dragSrcIdx = null;
    }

    function canvasDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = e.dataTransfer.getData('blockType') ? 'copy' : 'move';
        const ind = e.currentTarget.querySelector?.('.drop-indicator') || (e.currentTarget.classList.contains('drop-indicator') ? e.currentTarget : null);
        if (ind) ind.classList.add('visible');
    }

    function canvasDrop(e, newIdx) {
        e.preventDefault();
        e.stopPropagation();

        const type = e.dataTransfer.getData('blockType');
        const exId = e.dataTransfer.getData('exerciseId');

        if (type) {
            // Drop block type from palette
            addBlockByType(type);
            return;
        }

        if (exId && selectedIdx !== null) {
            addExerciseToSelected(parseInt(exId));
            return;
        }

        // Reorder
        if (dragSrcIdx !== null && dragSrcIdx !== newIdx) {
            const moved = blocks.splice(dragSrcIdx, 1)[0];
            const insertAt = dragSrcIdx < newIdx ? newIdx - 1 : newIdx;
            blocks.splice(insertAt, 0, moved);
            renderCanvas();
            selectedIdx = insertAt;
            renderProps(blocks[insertAt], insertAt);
            updateSummary();
        }
    }

    function exerciseDragStart(e, exId) {
        e.dataTransfer.setData('exerciseId', exId);
        e.dataTransfer.effectAllowed = 'copy';
    }

    function jumpToBlock(idx) { selectBlock(idx); }

    function updateSummary() {
        const total = blocks.reduce((s, b) => s + computeBlockDuration(b), 0);
        document.getElementById('sum-blocks')?.innerText != undefined && (document.getElementById('sum-blocks').innerText = blocks.length);
        const sumDur = document.getElementById('sum-duration');
        if (sumDur) sumDur.innerText = formatDuration(total);
    }

    function setBlockSpotify(blockIdx, uri, name) {
        if (!blocks[blockIdx]) return;
        blocks[blockIdx].spotify_uri = uri;
        blocks[blockIdx].spotify_name = name;
        renderProps(blocks[blockIdx], blockIdx);
        updateCanvas(blockIdx);
    }

    function setBlockProp(blockIdx, key, val) {
        if (!blocks[blockIdx]) return;
        blocks[blockIdx][key] = val;
        updateCanvas(blockIdx);
    }

    return {
        get blocks() { return blocks; },
        init, loadBlocks, addBlockByType, selectBlock,
        updateBlock, updateConfig, removeBlock, duplicateBlock,
        removeExFromBlock, updateExReps, addExerciseToSelected, randomFill,
        filterMuscle, setBlockSpotify, setBlockProp,
        exerciseDragStart, blockDragStart, blockDragEnd,
        canvasDragOver, canvasDrop, jumpToBlock,
    };
})();


// Expose global functions used inline
window.addBlockByType = t => GFBuilder.addBlockByType(t);
window.filterExercises = q => GFBuilder._filterEx?.(q);  // Will be set by builder
window.filterMuscle = btn => GFBuilder._filterMuscle?.(btn);

// Patch in filterExercises reference
(function () {
    const orig = GFBuilder;
    const oldLoad = orig.init.toString();
    // Re-expose internal filter functions via global
    const _origInit = orig.init;
    const _newInit = function (editSession) {
        _origInit(editSession);
        // Expose filter functions
        window.filterExercises = (q) => {
            let list = GFBuilder._exercises || [];
            // delegate to internal
            document.getElementById('ex-search') && GFBuilder._filterE?.(q);
        };
    };
    // Simple workaround: expose via data
    GFBuilder._filterE = (q) => { };
})();

// Actually let's just expose the functions directly at module level:
window.filterExercises = function (q) {
    // Rebuild from GFBuilder internal
    const allEx = [];
    document.querySelectorAll('.exercise-chip').forEach(el => {
        const name = el.dataset.exName || '';
        const muscle = el.dataset.muscle || '';
        el.style.display = (name.toLowerCase().includes(q.toLowerCase())) ? 'flex' : 'none';
    });
};

window.filterMuscle = function (btn) {
    GFBuilder.filterMuscle(btn);
};
