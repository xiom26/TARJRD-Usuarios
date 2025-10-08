(function(){
  const $ = (sel, ctx=document) => ctx.querySelector(sel);

  if(!document.getElementById('guc-table')){
    return;
  }

  // ---------- Crear ----------
  function openModal(){ $('#guc-mask').hidden = false; $('#guc-expediente').focus(); }
  function closeModal(){ $('#guc-mask').hidden = true; $('#guc-expediente').value=''; }

  function rowHTML(r){
    return `
      <tr data-id="${r.id}">
        <td class="guc-td-username">${r.username}</td>
        <td class="guc-td-password"><span class="guc-badge-green" aria-label="Contraseña generada">${r.password}</span></td>
        <td class="guc-td-entity">${r.entity ? r.entity : '-'}</td>
        <td class="guc-td-expediente">${r.expediente}</td>
        <td class="guc-td-created">${r.created_at}</td>
        <td>
          <div class="guc-actions">
            <button class="guc-icon guc-view" data-act="view" title="Ver detalles" type="button">👁️</button>
            <button class="guc-icon guc-edit" data-act="edit" title="Editar" type="button">✏️</button>
            <button class="guc-icon guc-del" data-act="delete" title="Eliminar" type="button">🗑️</button>
          </div>
        </td>
      </tr>
    `;
  }

  async function fetchList(){
    const res = await fetch(GUC.ajax, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ action:'guc_list', nonce:GUC.nonce })
    });
    const j = await res.json();
    if(!j.success){ alert(GUC.capErr); return; }
    const tbody = $('#guc-tbody');
    const empty = $('#guc-empty');
    if(!j.data.rows.length){
      tbody.innerHTML = '';
      empty.hidden = false;
      return;
    }

    empty.hidden = true;
    tbody.innerHTML = j.data.rows.map(rowHTML).join('');
  }

  async function createUser(){
    const expediente = $('#guc-expediente').value.trim();
    if (!expediente){ alert('Ingresa el Nro de Expediente'); return; }
    $('#guc-create').disabled = true;

    const res = await fetch(GUC.ajax, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ action:'guc_create', nonce:GUC.nonce, expediente })
    });
    const j = await res.json();
    $('#guc-create').disabled = false;

    if(!j.success){ alert(j.data?.msg || 'Error'); return; }
    const tbody = $('#guc-tbody');
    $('#guc-empty').hidden = true;
    tbody.insertAdjacentHTML('afterbegin', rowHTML(j.data.row));
    closeModal();
  }

  async function deleteRow(id, tr){
    if(!confirm('¿Eliminar este usuario?')) return;
    const res = await fetch(GUC.ajax, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ action:'guc_delete', nonce:GUC.nonce, id })
    });
    const j = await res.json();
    if(!j.success){ alert(j.data?.msg || 'No se pudo eliminar'); return; }
    tr.remove();
    const tbody = $('#guc-tbody');
    if(!tbody.children.length){
      $('#guc-empty').hidden = false;
    }
  }

  // ---------- Editar ----------
  const edit = {
    open(id, tr){
      edit.dirty = false;
      edit.id = id;
      edit.tr = tr;

      const username = tr.querySelector('.guc-td-username').innerText.trim();
      const password = tr.querySelector('.guc-td-password .guc-badge-green').innerText.trim();
      const entity   = tr.querySelector('.guc-td-entity').innerText.trim();
      const expediente = tr.querySelector('.guc-td-expediente').innerText.trim();

      $('#guc-edit-username').value   = username;
      $('#guc-edit-password').value   = password;
      $('#guc-edit-entity').value     = (entity === '-' ? '' : entity);
      $('#guc-edit-expediente').value = expediente;

      // estado inicial para detectar cambios
      edit.initial = {
        entity: $('#guc-edit-entity').value,
        expediente: $('#guc-edit-expediente').value
      };

      $('#guc-edit-mask').hidden = false;
      $('#guc-edit-entity').focus();
    },
    close(force=false){
      if(!force && edit.isDirty()){
        const ok = confirm('Tienes cambios sin guardar. ¿Cerrar de todos modos?');
        if(!ok) return;
      }
      $('#guc-edit-mask').hidden = true;
      edit.id = null;
      edit.tr = null;
      edit.initial = null;
    },
    isDirty(){
      if(!edit.initial) return false;
      return (
        $('#guc-edit-entity').value !== edit.initial.entity ||
        $('#guc-edit-expediente').value !== edit.initial.expediente
      );
    },
    async save(){
      const entity     = $('#guc-edit-entity').value.trim();
      const expediente = $('#guc-edit-expediente').value.trim();
      $('#guc-edit-save').disabled = true;

      const res = await fetch(GUC.ajax, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action:'guc_update',
          nonce:GUC.nonce,
          id: edit.id,
          entity,
          expediente
        })
      });
      const j = await res.json();
      $('#guc-edit-save').disabled = false;

      if(!j.success){ alert(j.data?.msg || 'No se pudo actualizar'); return; }

      // actualizar fila en la tabla
      edit.tr.querySelector('.guc-td-entity').innerText     = j.data.entity || '-';
      edit.tr.querySelector('.guc-td-expediente').innerText = j.data.expediente;

      edit.close(true);
    }
  };

  // ---------- Eventos ----------
  document.addEventListener('click', (e)=>{
    if(e.target.id === 'guc-open-modal'){ openModal(); }
    if(e.target.id === 'guc-close' || e.target.id === 'guc-cancel'){ closeModal(); }
    if(e.target.id === 'guc-create'){ createUser(); }
    if(e.target.closest('#guc-mask') && e.target.id === 'guc-mask'){ closeModal(); }

    // modal editar
    if(e.target.id === 'guc-edit-close' || e.target.id === 'guc-edit-cancel'){
      edit.close(false);
    }
    if(e.target.id === 'guc-edit-save'){ edit.save(); }
    if(e.target.closest('#guc-edit-mask') && e.target.id === 'guc-edit-mask'){ edit.close(false); }

    // acciones por fila
    const actBtn = e.target.closest('.guc-actions .guc-icon');
    if(actBtn){
      const tr = e.target.closest('tr');
      const id = parseInt(tr.dataset.id,10);
      const act = actBtn.dataset.act;

      if(act === 'view'){
        const u = tr.querySelector('.guc-td-username').innerText.trim();
        const p = tr.querySelector('.guc-td-password .guc-badge-green').innerText.trim();
        const en = tr.querySelector('.guc-td-entity').innerText.trim();
        const ex = tr.querySelector('.guc-td-expediente').innerText.trim();
        alert(`Usuario: ${u}\nContraseña: ${p}\nEntidad: ${en}\nExpediente: ${ex}`);
      }
      if(act === 'edit'){
        edit.open(id, tr);
      }
      if(act === 'delete'){
        deleteRow(id, tr);
      }
    }
  });

  // marca sucio cuando cambien los inputs del modal editar
  ['guc-edit-entity','guc-edit-expediente'].forEach(id=>{
    document.addEventListener('input', (e)=>{
      if(e.target.id === id){ edit.dirty = true; }
    });
  });

  // inicia
  document.addEventListener('DOMContentLoaded', fetchList);
})();
