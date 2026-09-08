import { ref } from "vue";

export function useNotifier() {
    const snackbar = ref(false);
    const snackText = ref("");
    const snackColor = ref("success");

    function notify(message, color = "success") {
        snackText.value = message;
        snackColor.value = color;
        snackbar.value = true;
    }

    function notifyError(message) { notify(message, "error"); }

    return { snackbar, snackText, snackColor, notify, notifyError };
}