import { createApp } from "vue";

createApp({
    compilerOptions: {
        delimiters: ["${", "}$"]
    },
    data() {
        return {
            timeout: null,
            isLoading: false,
            questions: null
        }
    },
    methods: {
        updateInput(event: KeyboardEvent) {
            clearTimeout(this.timeout);
            this.timeout = setTimeout(async () => {
                const value = this.$refs.input.value;

                if (value?.length) {
                    try {
                        this.isLoading = true;

                        const response = await fetch(`/question/search/${this.$refs.input.value}`)
                        const body = await response.json();
                        this.isLoading = false;
                        this.questions = JSON.parse(body);
                        console.log(this.questions);

                    }
                    catch (e) {
                        this.questions = null;
                        this.isLoading = false;
                    }
                } else {
                    this.questions = null;
                }



            }, 1000)
        }
    }
}).mount('#search')