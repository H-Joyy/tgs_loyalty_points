/**
 * TGS Loyalty Points — Admin JS
 *
 * Shared utilities cho các view loyalty-*.
 */
(function ($) {
  "use strict";

  window.TgsLoyaltyUtils = {
    /**
     * Format số theo locale VN.
     */
    formatNumber: function (n) {
      return Number(n).toLocaleString("vi-VN");
    },

    /**
     * Label cho loại chính sách.
     */
    typeLabel: function (type) {
      var labels = {
        1: "Theo số tiền",
        2: "Theo sản phẩm",
        3: "Nhân hệ số",
        4: "Bonus sự kiện",
      };
      return labels[type] || "Khác";
    },

    /**
     * Bootstrap color cho loại.
     */
    typeColor: function (type) {
      var colors = { 1: "primary", 2: "success", 3: "warning", 4: "info" };
      return colors[type] || "secondary";
    },

    /**
     * Label + color cho trạng thái.
     */
    statusLabel: function (status) {
      var labels = { 0: "Nháp", 1: "Hoạt động", 2: "Hết hạn" };
      return labels[status] || "?";
    },

    statusColor: function (status) {
      var colors = { 0: "secondary", 1: "success", 2: "danger" };
      return colors[status] || "secondary";
    },

    /**
     * Label cho loại giao dịch điểm.
     */
    logTypeLabel: function (logType) {
      var labels = {
        earn: "Tích",
        redeem: "Đổi",
        adjust: "Điều chỉnh",
        expire: "Hết hạn",
        refund: "Hoàn",
      };
      return labels[logType] || logType;
    },

    /**
     * Toast notification (nếu có Sneat/Bootstrap Toast).
     */
    toast: function (message, type) {
      // Fallback to alert
      alert(message);
    },
  };
})(jQuery);
