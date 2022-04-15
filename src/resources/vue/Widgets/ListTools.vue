<template>
    <div></div>
</template>

<script>

    const ListSearch = {
        props: {
            onSearch: { type: Function, default: () => {} },
            placeholder: { type: String, default: '' }
        },
        data() {
            return {
                search: ''
            }
        },
        template: `<form @submit.prevent="onSearch(search)" id="search-form" class="input-group" style="flex:1">
                <input class="form-control" type="text" :placeholder="placeholder" v-model="search">
                <button type="submit" class="btn btn-primary"><svg-icon icon="search"></svg-icon> {{ $t('btn.search') }}</button>
            </form>`
    }

    const ListFoot = {
        props: {
            colspan: { type: Number, default: 1 },
            text: { type: String, default: '' }
        },
        template: `<tfoot class="table-fake-body"><tr><td :colspan="colspan">{{ text }}</td></tr></tfoot>`
    }

    const ListMore = {
        props: {
            onClick: { type: Function, default: () => {} }
        },
        template: `<div class="text-center p-3 more-loader">
                <button class="btn btn-secondary" @click="onClick({})">{{ $t('nav.more') }}</button>
            </div>`
    }

    export default {
        components: {
            ListFoot,
            ListMore,
            ListSearch
        },
        data() {
            return {
                currentSearch: '',
                hasMore: false,
                page: 1
            }
        },
        methods: {
            listSearch(name, url, params) {
                let loader
                let get = params.get || {}

                if (params) {
                    if (params.reset || params.init) {
                        this[name] = []
                        this.page = 0
                    }

                    get.page = params.page || (this.page + 1)

                    if ('search' in params) {
                        get.search = params.search
                        this.currentSearch = params.search
                        this.hasMore = false
                    } else {
                        get.search = this.currentSearch
                    }

                    if (!params.init) {
                        loader = $(this.$el).find('.more-loader')
                        if (!loader.length || get.page == 1) {
                            loader = $(this.$el).find('tfoot td')
                        }
                    }
                } else {
                    this.currentSearch = null
                }

                if (params && params.init) {
                    this.$root.startLoading()
                } else {
                    this.$root.addLoader(loader)
                }

                const finish = () => {
                    if (params && params.init) {
                        this.$root.stopLoading()
                    } else {
                        this.$root.removeLoader(loader)
                    }
                }

                axios.get(url, { params: get })
                    .then(response => {
                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this[name], this[name].length, response.data.list[i])
                        }

                        this.hasMore = response.data.hasMore
                        this.page = response.data.page || 1

                        finish()
                    })
                    .catch(error => {
                        finish()
                    })
            }
        }
    }
</script>
