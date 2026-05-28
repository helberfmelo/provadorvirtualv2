<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { MeasurementTable } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

const tables = ref<MeasurementTable[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadTables()
})

async function loadTables() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/measurement-tables')
    tables.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as tabelas.'
  } finally {
    loading.value = false
  }
}

async function removeTable(table: MeasurementTable) {
  await api.delete(`/measurement-tables/${table.id}`)
  showFeedback({
    status: 'success',
    title: 'Tabela removida',
    message: 'A tabela de medidas foi removida da empresa.',
  })
  await loadTables()
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Tabelas</span>
        <h1>Tabelas de medidas</h1>
        <p>Defina faixas por tamanho e mantenha cada produto com uma base de recomendação confiável.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadTables">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/app/tabelas-de-medidas/nova">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova tabela
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Tabelas cadastradas</h2>
        <span>{{ loading ? 'carregando' : `${tables.length} tabelas` }}</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tabela</th>
              <th>Tipo</th>
              <th>Base</th>
              <th>Sistema</th>
              <th>Gênero</th>
              <th>Linhas</th>
              <th>Produtos</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tables.length">
              <td colspan="9">Nenhuma tabela cadastrada.</td>
            </tr>
            <tr v-for="table in tables" :key="table.id">
              <td>
                <strong>{{ table.name }}</strong>
                <small>{{ table.source }}</small>
              </td>
              <td>{{ table.product_type }}</td>
              <td>{{ table.measurement_target === 'garment' ? 'Peça' : table.measurement_target === 'mixed' ? 'Corpo + peça' : 'Corpo' }}</td>
              <td>{{ table.size_system === 'br_numeric' ? 'BR numérico' : table.size_system === 'international' ? 'Internacional' : table.size_system === 'custom' ? 'Personalizado' : 'BR letras' }}</td>
              <td>{{ table.gender || '-' }}</td>
              <td>{{ table.rows_count ?? 0 }}</td>
              <td>{{ table.products_count ?? 0 }}</td>
              <td>
                <span class="status-pill" :class="{ ok: table.status === 'active', warning: table.status !== 'active' }">
                  {{ table.status === 'active' ? 'Ativa' : table.status }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/app/tabelas-de-medidas/${table.id}/editar`" title="Editar">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" title="Remover tabela" @click="removeTable(table)">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
