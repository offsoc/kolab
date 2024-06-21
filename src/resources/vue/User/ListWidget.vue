<template>
    <list-table :list="list" :current="current" :setup="setup"></list-table>
</template>

<script>
    import { ListTable } from '../Widgets/ListTools'

    export default {
        components: {
            ListTable
        },
        props: {
            list: { type: Array, default: () => [] },
            current: { type: Object, default: () => null }
        },
        data() {
            return {
                setup: {
                    model: 'user',
                    columns: [
                        {
                            prop: 'email',
                            icon: 'user',
                            label: 'form.primary-email',
                            link: true
                        }
                    ]
                }
            }
        }
    }
</script>
