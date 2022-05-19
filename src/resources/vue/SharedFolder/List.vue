<template>
    <div class="container">
        <div class="card" id="folder-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('shf.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded()" to="shared-folder/new" class="btn-success float-end" icon="folder-open">
                        {{ $t('shf.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <list-widget :list="folders"></list-widget>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListWidget from './ListWidget'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faFolderOpen').definition,
    )

    export default {
        components: {
            ListWidget
        },
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
