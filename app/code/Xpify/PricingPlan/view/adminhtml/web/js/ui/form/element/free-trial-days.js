define(["Magento_Ui/js/form/element/abstract"], function (Abstract) {
  "use strict";

  return Abstract.extend({
    defaults: {
      imports: {
        toggleRequired: "${ $.parentName }.enable_free_trial:value",
      },
    },

    initialize() {
      this._super();
      // Khởi tạo theo giá trị hiện có (khi edit)
      const val = this.source.get(this.parentName + ".enable_free_trial");
      this.toggleRequired(val);
      return this;
    },

    toggleRequired(value) {
      value = parseInt(value, 10) || 0;

      if (value === 1) {
        this.validation["required-entry"] = true;
        this.required(true);
      } else {
        // Gỡ rule required
        if (this.validation["required-entry"]) {
          delete this.validation["required-entry"];
          this.required(false);
        }
        // Xóa trạng thái lỗi nếu có
        this.error(false);
      }
    },
  });
});
