declare module 'luminous-lightbox' {
  export interface LuminousOptions {
    namespace?: string;
    sourceAttribute?: string;
    caption?: string | ((trigger: HTMLElement) => string);
    openTrigger?: string;
    closeTrigger?: string;
    closeWithEscape?: boolean;
    closeOnScroll?: boolean;
    showCloseButton?: boolean;
    appendToNode?: HTMLElement;
    appendToSelector?: string;
    onOpen?: () => void;
    onClose?: () => void;
    includeImgixJSClass?: boolean;
    injectBaseStyles?: boolean;
  }

  export interface LuminousGalleryOptions {
    arrowNavigation?: boolean;
  }

  export class Luminous {
    constructor(trigger: HTMLElement, options?: LuminousOptions);
    open(): void;
    close(): void;
    destroy(): void;
  }

  export class LuminousGallery {
    constructor(
      triggers: NodeListOf<HTMLElement> | HTMLElement[],
      galleryOptions?: LuminousGalleryOptions,
      luminousOptions?: LuminousOptions,
    );
    destroy(): void;
  }
}
