<template>
    <div class="list-input" :id="id">
        <div class="input-group">
            <input :id="id + '-input'" type="text" class="form-control main-input" @keydown="keyDown">
            <div class="input-group-append">
                <a href="#" class="btn btn-outline-secondary" @click.prevent="addItem">
                    <svg-icon icon="plus"></svg-icon>
                    <span class="sr-only">Add</span>
                </a>
            </div>
        </div>
        <div class="input-group" v-for="(item, index) in list" :key="index">
            <input type="text" class="form-control" v-model="list[index]">
            <div class="input-group-append">
                <a href="#" class="btn btn-outline-secondary" @click.prevent="deleteItem(index)">
                    <svg-icon icon="trash-alt"></svg-icon>
                    <span class="sr-only">Delete</span>
                </a>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            list: { type: Array, default: () => [] },
            id: { type: String, default: '' }
        },
        methods: {
            addItem() {
                let input = $(this.$el).find('.main-input')
                let value = input.val()

                if (value) {
                    this.list.push(value)
                    input.val('').focus()
                }
            },
            deleteItem(index) {
                this.$delete(this.list, index)

                if (this.list.length == 1) {
                    $(this.$el).removeClass('is-invalid')
                }
            },
            keyDown(e) {
                if (e.which == 13 && e.target.value) {
                    this.addItem()
                    e.preventDefault()
                }
            }
        }
    }
</script>
