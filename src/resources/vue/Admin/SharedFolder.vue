<template>
    <div v-if="folder.id" class="container">
        <div class="card" id="folder-info">
            <div class="card-body">
                <div class="card-title">{{ folder.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="folderid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="folderid">
                                    {{ folder.id }} <span class="text-muted">({{ folder.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span :class="$root.statusClass(folder) + ' form-control-plaintext'" id="status">{{ $root.statusText(folder) }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="name" class="col-sm-4 col-form-label">{{ $t('form.name') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="name">{{ folder.name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="type" class="col-sm-4 col-form-label">{{ $t('form.type') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="type">{{ $t('shf.type-' + folder.type) }}</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-settings" href="#folder-settings" role="tab" aria-controls="folder-settings" aria-selected="false" @click="$root.tab">
                    {{ $t('form.settings') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-aliases" href="#folder-aliases" role="tab" aria-controls="folder-aliases" aria-selected="false" @click="$root.tab">
                    {{ $t('user.aliases-email') }} ({{ folder.aliases.length }})
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="folder-settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="acl" class="col-sm-4 col-form-label">{{ $t('form.acl') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="acl">
                                        <span v-if="folder.config.acl.length">
                                            <span v-for="(entry, index) in folder.config.acl" :key="index">
                                                {{ entry.replace(',', ':') }}<br>
                                            </span>
                                        </span>
                                        <span v-else>{{ $t('form.none') }}</span>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="folder-aliases" role="tabpanel" aria-labelledby="tab-aliases">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('form.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(alias, index) in folder.aliases" :id="'alias' + index" :key="index">
                                    <td>{{ alias }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>{{ $t('shf.aliases-none') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                folder: { config: {}, aliases: [] }
            }
        },
        created() {
            axios.get('/api/v4/shared-folders/' + this.$route.params.folder, { loader: true })
                .then(response => {
                    this.folder = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
