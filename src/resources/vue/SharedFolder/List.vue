<template>
    <div class="container">
        <div class="card" id="folder-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('shf.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded()" to="shared-folder/new" class="btn-success float-end" icon="gear">
                        {{ $t('shf.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('form.name') }}</th>
                                <th scope="col">{{ $t('form.type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="folder in folders" :key="folder.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="folder-open" :class="$root.statusClass(folder)" :title="$root.statusText(folder)"></svg-icon>
                                    <router-link :to="{ path: 'shared-folder/' + folder.id }">{{ folder.name }}</router-link>
                                </td>
                                <td>{{ $t('shf.type-' + folder.type) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="2">{{ $t('shf.list-empty') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faFolderOpen').definition,
        require('@fortawesome/free-solid-svg-icons/faGear').definition,
    )

    export default {
        data() {
            return {
                folders: []
            }
        },
        created() {
            axios.get('/api/v4/shared-folders', { loader: true })
                .then(response => {
                    this.folders = response.data.list
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
