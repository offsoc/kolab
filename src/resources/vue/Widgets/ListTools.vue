<template>
    <div></div>
</template>

<script>

    export const ListSearch = {
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
                <button type="submit" class="btn btn-primary"><svg-icon icon="magnifying-glass"></svg-icon> {{ $t('btn.search') }}</button>
            </form>`
    }

    export const ListFoot = {
        props: {
            colspan: { type: Number, default: 1 },
            text: { type: String, default: '' }
        },
        template: `<tfoot class="table-fake-body"><tr><td :colspan="colspan">{{ text }}</td></tr></tfoot>`
    }

    export const ListMore = {
        props: {
            onClick: { type: Function, default: () => {} }
        },
        template: `<div class="text-center p-3 more-loader">
                <button class="btn btn-secondary" @click="onClick({})">{{ $t('nav.more') }}</button>
            </div>`
    }

    export const ListTable = {
        components: {
            ListFoot
        },
        props: {
            current: { type: Object, default: () => null },
            list: { type: Array, default: () => [] },
            setup: { type: Object, default: () => {} },
        },
        methods: {
            content(column, item) {
                if (column.contentLabel) {
                    return this.$t(column.contentLabel(item))
                }
                if (column.content) {
                    return column.content(item)
                }
                return item[column.prop]
            },
            label(label) {
                let l = `${this.setup.prefix || this.setup.model}${label}`
                return this.$te(l) ? l : `form${label}`
            },
            url(item) {
                return `/${this.setup.model}/${item.id}`
            }
        },
        template:
            `<table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th v-for="column in setup.columns" scope="col">{{ $t(column.label || label('.' + column.prop)) }}</th>
                        <th v-if="setup.buttons" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in list" :key="item.id || index" :id="setup.model ? (setup.model + (item.id || index)) : null" @click="$root.clickRecord">
                        <td v-for="column in setup.columns" :key="column.prop + (item.id || index)" :class="column.className">
                            <svg-icon v-if="column.icon" :icon="column.icon" :class="$root.statusClass(item)" :title="$root.statusText(item)"></svg-icon>
                            <router-link v-if="column.link && (!current || current.id != item.id)" :to="url(item)">{{ content(column, item) }}</router-link>
                            <slot v-else-if="column.contentSlot" :name="column.contentSlot" v-bind:item="item"></slot>
                            <span v-else>{{ content(column, item) }}</span>
                        </td>
                        <td v-if="setup.buttons" class="buttons">
                             <slot name="buttons" v-bind:item="item"></slot>
                        </td>
                    </tr>
                </tbody>
                <list-foot :text="$t(setup.footLabel || label('.list-empty'))" :colspan="setup.columns.length + (setup.buttons ? 1 : 0)"></list-foot>
            </table>`
    }

    export default {
        components: {
            ListFoot,
            ListMore,
            ListSearch,
            ListTable
        },
        data() {
            return {
                currentSearch: '',
                currentParent: '',
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

                    if ('parent' in params) {
                        get.parent = params.parent
                        this.currentParent = params.parent
                    } else {
                        get.parent = this.currentParent
                    }

                    if (!params.init) {
                        loader = $(this.$el).find('.more-loader')
                        if (!loader.length || get.page == 1) {
                            loader = $(this.$el).find('tfoot td')
                        }
                    } else {
                        loader = true
                    }
                } else {
                    this.currentSearch = null
                    this.currentParent = null
                }

                axios.get(url, { params: get, loader })
                    .then(response => {
                        // Note: In Vue we can't just use .concat()
                        for (let i in response.data.list) {
                            this.$set(this[name], this[name].length, response.data.list[i])
                        }

                        this.hasMore = response.data.hasMore
                        this.page = get.page || 1
                    })
            }
        }
    }
</script>
