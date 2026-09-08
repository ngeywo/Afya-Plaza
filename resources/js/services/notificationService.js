import api from "./api.js";

export default {
  list(params = {}) { return api.get("/notifications", { params }); },
  unreadCount() { return api.get("/notifications/unread-count"); },
  markRead(id) { return api.patch(`/notifications/${id}/read`); },
  markAllRead() { return api.patch("/notifications/read-all"); },
};
