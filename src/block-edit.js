import QueryBuilderModal from './components/QueryBuilderModal';
import metadata from '../block.json';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import {
  PanelBody,
  SelectControl,
  RangeControl,
  ToggleControl,
  TextControl,
  Button,
  Placeholder,
  __experimentalToggleGroupControl as ToggleGroupControl,
  __experimentalToggleGroupControlOption as ToggleGroupControlOption,
  __experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
  __experimentalToolsPanel as ToolsPanel,
  __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useState, useEffect, createPortal } from '@wordpress/element';
import { alignLeft, alignCenter, alignRight } from '@wordpress/icons';

const FONT_FAMILY_OPTIONS = [
  { label: 'Default (inherit)', value: '' },
  { label: '— System —', value: '__sep__', disabled: true },
  { label: 'System UI', value: 'system-ui, sans-serif' },
  { label: 'Arial', value: 'Arial, sans-serif' },
  { label: 'Georgia', value: 'Georgia, serif' },
  { label: 'Courier New', value: "'Courier New', monospace" },
  { label: '— Google Sans-Serif —', value: '__sep__', disabled: true },
  { label: 'Inter', value: "'Inter', sans-serif" },
  { label: 'Roboto', value: "'Roboto', sans-serif" },
  { label: 'Open Sans', value: "'Open Sans', sans-serif" },
  { label: 'Lato', value: "'Lato', sans-serif" },
  { label: 'Montserrat', value: "'Montserrat', sans-serif" },
  { label: 'Poppins', value: "'Poppins', sans-serif" },
  { label: 'Nunito', value: "'Nunito', sans-serif" },
  { label: 'Raleway', value: "'Raleway', sans-serif" },
  { label: 'Ubuntu', value: "'Ubuntu', sans-serif" },
  { label: 'Noto Sans', value: "'Noto Sans', sans-serif" },
  { label: 'Source Sans 3', value: "'Source Sans 3', sans-serif" },
  { label: 'Oswald', value: "'Oswald', sans-serif" },
  { label: 'Mulish', value: "'Mulish', sans-serif" },
  { label: 'Quicksand', value: "'Quicksand', sans-serif" },
  { label: 'Barlow', value: "'Barlow', sans-serif" },
  { label: 'DM Sans', value: "'DM Sans', sans-serif" },
  { label: 'Figtree', value: "'Figtree', sans-serif" },
  { label: 'Outfit', value: "'Outfit', sans-serif" },
  { label: 'Plus Jakarta Sans', value: "'Plus Jakarta Sans', sans-serif" },
  { label: 'Manrope', value: "'Manrope', sans-serif" },
  { label: 'Work Sans', value: "'Work Sans', sans-serif" },
  { label: 'Rubik', value: "'Rubik', sans-serif" },
  { label: 'Karla', value: "'Karla', sans-serif" },
  { label: 'Cabin', value: "'Cabin', sans-serif" },
  { label: '— Google Serif —', value: '__sep__', disabled: true },
  { label: 'Playfair Display', value: "'Playfair Display', serif" },
  { label: 'Merriweather', value: "'Merriweather', serif" },
  { label: 'Lora', value: "'Lora', serif" },
  { label: 'PT Serif', value: "'PT Serif', serif" },
  { label: 'Cormorant', value: "'Cormorant', serif" },
  { label: 'Libre Baskerville', value: "'Libre Baskerville', serif" },
  { label: 'EB Garamond', value: "'EB Garamond', serif" },
  { label: 'Crimson Text', value: "'Crimson Text', serif" },
  { label: 'Spectral', value: "'Spectral', serif" },
  { label: 'DM Serif Display', value: "'DM Serif Display', serif" },
  { label: '— Google Display —', value: '__sep__', disabled: true },
  { label: 'Bebas Neue', value: "'Bebas Neue', sans-serif" },
  { label: 'Righteous', value: "'Righteous', sans-serif" },
  { label: 'Pacifico', value: "'Pacifico', cursive" },
  { label: 'Abril Fatface', value: "'Abril Fatface', serif" },
  { label: '— Google Mono —', value: '__sep__', disabled: true },
  { label: 'Roboto Mono', value: "'Roboto Mono', monospace" },
  { label: 'JetBrains Mono', value: "'JetBrains Mono', monospace" },
  { label: 'Fira Code', value: "'Fira Code', monospace" },
  { label: 'Space Mono', value: "'Space Mono', monospace" },
  { label: '— Custom —', value: '__sep__', disabled: true },
  { label: 'Custom (type below)', value: '__custom__' },
];

const FONT_WEIGHT_OPTIONS = [
  { label: 'Default', value: '' },
  { label: '100', value: '100' },
  { label: '200', value: '200' },
  { label: '300', value: '300' },
  { label: '400 (normal)', value: '400' },
  { label: '500', value: '500' },
  { label: '600', value: '600' },
  { label: '700 (bold)', value: '700' },
  { label: '800', value: '800' },
  { label: '900', value: '900' },
  { label: 'Normal', value: 'normal' },
  { label: 'Bold', value: 'bold' },
];

const TEXT_TRANSFORM_OPTIONS = [
  { label: 'Default', value: '' },
  { label: 'None', value: 'none' },
  { label: 'Uppercase', value: 'uppercase' },
  { label: 'Lowercase', value: 'lowercase' },
  { label: 'Capitalize', value: 'capitalize' },
];

function cardPartFontSizePx(attributes, prefix) {
  const v = attributes[`${prefix}FontSize`];
  if (typeof v === 'number' && !Number.isNaN(v)) {
    return Math.min(96, Math.max(0, v));
  }
  if (typeof v === 'string' && v) {
    const m = v.match(/^(\d+)/);
    if (m) return Math.min(96, parseInt(m[1], 10));
  }
  return 0;
}

function typographyResetAttributes(prefix) {
  return {
    [`${prefix}FontSize`]: 0,
    [`${prefix}FontFamily`]: '',
    [`${prefix}FontStyle`]: '',
    [`${prefix}FontWeight`]: '',
    [`${prefix}LineHeight`]: '',
    [`${prefix}LetterSpacing`]: '',
    [`${prefix}TextTransform`]: '',
  };
}

const QF_PRESET_COLORS = [
  '#ffffff',
  '#f1f1f1',
  '#1a1a1a',
  '#185FA5',
  '#533AB7',
  '#D85A30',
  '#0F6E56',
  '#993556',
];

function QFColorControl({ label, value, onChange }) {
  const [pickerOpen, setPickerOpen] = useState(false);
  const [inputVal, setInputVal] = useState(value || '');

  useEffect(() => {
    setInputVal(value || '');
  }, [value]);

  const isPreset = QF_PRESET_COLORS.includes(value);
  const hasCustom = value && !isPreset;

  return (
    <div style={{ marginBottom: 12 }}>
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: 5,
        }}
      >
        <span
          style={{
            fontSize: '11px',
            color: 'var(--color-text-secondary)',
          }}
        >
          {label}
        </span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          {value && (
            <div
              style={{
                width: 14,
                height: 14,
                borderRadius: '50%',
                background: value,
                border: '0.5px solid var(--color-border-tertiary)',
              }}
            />
          )}
          {value && (
            <button
              type="button"
              style={{
                fontSize: '10px',
                color: 'var(--color-text-secondary)',
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                padding: 0,
                textDecoration: 'underline',
              }}
              onClick={() => {
                onChange('');
                setInputVal('');
                setPickerOpen(false);
              }}
            >
              clear
            </button>
          )}
        </div>
      </div>

      <div
        style={{
          display: 'flex',
          gap: 5,
          alignItems: 'center',
          flexWrap: 'wrap',
          marginBottom: pickerOpen ? 8 : 0,
        }}
      >
        {QF_PRESET_COLORS.map((color) => (
          <div
            key={color}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onChange(color);
                setPickerOpen(false);
              }
            }}
            onClick={() => {
              onChange(color);
              setPickerOpen(false);
            }}
            style={{
              width: 20,
              height: 20,
              borderRadius: '50%',
              background: color,
              cursor: 'pointer',
              flexShrink: 0,
              border:
                value === color
                  ? '2px solid var(--color-text-primary)'
                  : '0.5px solid var(--color-border-tertiary)',
              outline:
                value === color
                  ? '1px solid var(--color-background-primary)'
                  : 'none',
              outlineOffset: '-3px',
            }}
          />
        ))}
        <div
          role="button"
          tabIndex={0}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              setPickerOpen((p) => !p);
            }
          }}
          onClick={() => setPickerOpen((p) => !p)}
          title="Custom color"
          style={{
            width: 20,
            height: 20,
            borderRadius: '50%',
            cursor: 'pointer',
            flexShrink: 0,
            background:
              'conic-gradient(red, yellow, lime, cyan, blue, magenta, red)',
            border: hasCustom
              ? '2px solid var(--color-text-primary)'
              : '0.5px solid transparent',
            position: 'relative',
          }}
        >
          <div
            style={{
              position: 'absolute',
              inset: 3,
              borderRadius: '50%',
              background: 'var(--color-background-primary)',
            }}
          />
        </div>
      </div>

      {pickerOpen && (
        <div
          style={{
            background: 'var(--color-background-secondary)',
            border: '0.5px solid var(--color-border-tertiary)',
            borderRadius: 'var(--border-radius-md)',
            padding: 8,
            marginTop: 4,
          }}
        >
          <div
            style={{
              fontSize: '10px',
              color: 'var(--color-text-secondary)',
              marginBottom: 4,
            }}
          >
            Accepts: #hex, rgb(), rgba()
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <input
              type="text"
              value={inputVal}
              placeholder="#hex or rgba(r,g,b,a)"
              onChange={(e) => setInputVal(e.target.value)}
              style={{
                flex: 1,
                padding: '4px 8px',
                fontSize: '11px',
                border: '0.5px solid var(--color-border-secondary)',
                borderRadius: 4,
                background: 'var(--color-background-primary)',
                color: 'var(--color-text-primary)',
                fontFamily: 'var(--font-mono)',
              }}
            />
            <button
              type="button"
              onClick={() => {
                if (inputVal) {
                  onChange(inputVal);
                  setPickerOpen(false);
                }
              }}
              style={{
                padding: '4px 10px',
                fontSize: '11px',
                border: '0.5px solid var(--color-border-secondary)',
                borderRadius: 4,
                cursor: 'pointer',
                background: 'var(--color-background-primary)',
                color: 'var(--color-text-primary)',
                fontFamily: 'var(--font-sans)',
              }}
            >
              Apply
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function AlignmentToggleGroup({ label, attrName, value, setAttributes }) {
  return (
    <ToggleGroupControl
      label={label}
      value={value || ''}
      onChange={(val) => setAttributes({ [attrName]: val ?? '' })}
      isBlock
    >
      <ToggleGroupControlOption value="" label="—" />
      <ToggleGroupControlOptionIcon
        value="left"
        icon={alignLeft}
        label="Left"
      />
      <ToggleGroupControlOptionIcon
        value="center"
        icon={alignCenter}
        label="Center"
      />
      <ToggleGroupControlOptionIcon
        value="right"
        icon={alignRight}
        label="Right"
      />
    </ToggleGroupControl>
  );
}

function TypographyToolsPanel({
  prefix,
  panelLabel,
  show,
  attributes,
  setAttributes,
}) {
  if (!show) {
    return null;
  }
  const px = cardPartFontSizePx(attributes, prefix);
  return (
    <ToolsPanel
      label={panelLabel}
      resetAll={() => setAttributes(typographyResetAttributes(prefix))}
    >
      <ToolsPanelItem
        label="Font size"
        hasValue={() => cardPartFontSizePx(attributes, prefix) > 0}
        onDeselect={() => setAttributes({ [`${prefix}FontSize`]: 0 })}
        isShownByDefault
      >
        <RangeControl
          label="Font size (px)"
          help={px === 0 ? '0 = inherit theme size' : undefined}
          value={px}
          onChange={(val) =>
            setAttributes({
              [`${prefix}FontSize`]: typeof val === 'number' ? val : 0,
            })
          }
          min={0}
          max={48}
          step={1}
        />
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Font family"
        hasValue={() => !!attributes[`${prefix}FontFamily`]}
        onDeselect={() => setAttributes({ [`${prefix}FontFamily`]: '' })}
        isShownByDefault
      >
        {(() => {
          const stored = attributes[`${prefix}FontFamily`] || '';
          const isKnown = FONT_FAMILY_OPTIONS.some(
            (o) =>
              o.value === stored &&
              o.value !== '' &&
              o.value !== '__sep__' &&
              o.value !== '__custom__'
          );
          const dropdownVal =
            stored === '' ? '' : isKnown ? stored : '__custom__';

          return (
            <>
              <SelectControl
                label="Font family"
                value={dropdownVal}
                options={FONT_FAMILY_OPTIONS.filter(
                  (o) => o.value !== '__sep__' || o.disabled
                )}
                onChange={(val) => {
                  if (val === '__sep__') return;
                  if (val === '__custom__') {
                    setAttributes({ [`${prefix}FontFamily`]: '__custom__' });
                  } else {
                    setAttributes({ [`${prefix}FontFamily`]: val });
                  }
                }}
              />
              {(stored === '__custom__' || (stored && !isKnown)) && (
                <TextControl
                  label="Font name"
                  help="e.g. 'My Font', sans-serif — must be loaded by your theme"
                  value={stored === '__custom__' ? '' : stored}
                  placeholder="'Font Name', sans-serif"
                  onChange={(val) =>
                    setAttributes({ [`${prefix}FontFamily`]: val })
                  }
                />
              )}
            </>
          );
        })()}
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Font weight"
        hasValue={() => !!attributes[`${prefix}FontWeight`]}
        onDeselect={() => setAttributes({ [`${prefix}FontWeight`]: '' })}
        isShownByDefault
      >
        <SelectControl
          label="Font weight"
          value={attributes[`${prefix}FontWeight`] || ''}
          options={FONT_WEIGHT_OPTIONS}
          onChange={(val) =>
            setAttributes({ [`${prefix}FontWeight`]: val })
          }
        />
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Font style"
        hasValue={() => !!attributes[`${prefix}FontStyle`]}
        onDeselect={() => setAttributes({ [`${prefix}FontStyle`]: '' })}
        isShownByDefault
      >
        <SelectControl
          label="Font style"
          value={attributes[`${prefix}FontStyle`] || ''}
          options={[
            { label: 'Default (inherit)', value: '' },
            { label: 'Normal', value: 'normal' },
            { label: 'Italic', value: 'italic' },
          ]}
          onChange={(val) =>
            setAttributes({ [`${prefix}FontStyle`]: val })
          }
        />
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Text transform"
        hasValue={() => !!attributes[`${prefix}TextTransform`]}
        onDeselect={() =>
          setAttributes({ [`${prefix}TextTransform`]: '' })
        }
        isShownByDefault
      >
        <SelectControl
          label="Text transform"
          value={attributes[`${prefix}TextTransform`] || ''}
          options={TEXT_TRANSFORM_OPTIONS}
          onChange={(val) =>
            setAttributes({ [`${prefix}TextTransform`]: val })
          }
        />
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Line height"
        hasValue={() => !!attributes[`${prefix}LineHeight`]}
        onDeselect={() => setAttributes({ [`${prefix}LineHeight`]: '' })}
        isShownByDefault
      >
        <TextControl
          label="Line height"
          help="e.g. 1.4 or 24px"
          value={attributes[`${prefix}LineHeight`] || ''}
          onChange={(val) =>
            setAttributes({ [`${prefix}LineHeight`]: val })
          }
        />
      </ToolsPanelItem>
      <ToolsPanelItem
        label="Letter spacing"
        hasValue={() => !!attributes[`${prefix}LetterSpacing`]}
        onDeselect={() =>
          setAttributes({ [`${prefix}LetterSpacing`]: '' })
        }
        isShownByDefault
      >
        <TextControl
          label="Letter spacing"
          help="e.g. 0.02em, 1px"
          value={attributes[`${prefix}LetterSpacing`] || ''}
          onChange={(val) =>
            setAttributes({ [`${prefix}LetterSpacing`]: val })
          }
        />
      </ToolsPanelItem>
    </ToolsPanel>
  );
}

function EditComponent({ attributes, setAttributes }) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const hasQuery = !!(attributes.graphState || attributes.logicJson);
  const blockProps = useBlockProps({ className: 'qf-block-editor-wrap' });

  const portalTarget = (() => {
    try {
      return window.top.document.body;
    } catch (e) {
      return document.body;
    }
  })();

  useEffect(() => {
    if (!isModalOpen) return;

    const topDoc = portalTarget.ownerDocument;
    const injected = [];

    document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
      if (topDoc.querySelector(`link[href="${link.href}"]`)) return;
      const clone = topDoc.createElement('link');
      clone.rel = 'stylesheet';
      clone.href = link.href;
      topDoc.head.appendChild(clone);
      injected.push(clone);
    });

    document.querySelectorAll('style').forEach((style) => {
      const clone = topDoc.createElement('style');
      clone.textContent = style.textContent;
      topDoc.head.appendChild(clone);
      injected.push(clone);
    });

    return () => {
      injected.forEach((el) => el.parentNode && el.parentNode.removeChild(el));
    };
  }, [isModalOpen]);

  return (
    <>
      <InspectorControls>
        <PanelBody title="Layout" initialOpen={true}>
          <SelectControl
            label="Columns"
            value={String(attributes.columns)}
            options={['1', '2', '3', '4', '5', '6'].map((n) => ({
              label: n,
              value: n,
            }))}
            onChange={(val) => setAttributes({ columns: Number(val) })}
          />
          <RangeControl
            label="Column Gap (px)"
            value={attributes.columnGap}
            onChange={(val) => setAttributes({ columnGap: val })}
            min={0}
            max={100}
          />
          <RangeControl
            label="Row Gap (px)"
            value={attributes.rowGap}
            onChange={(val) => setAttributes({ rowGap: val })}
            min={0}
            max={100}
          />
        </PanelBody>

        <PanelBody title="Card Style" initialOpen={false}>
          <SelectControl
            label="Card Style"
            value={attributes.cardStyle}
            options={[
              { label: 'Vertical Card', value: 'vertical' },
              { label: 'Horizontal Card', value: 'horizontal' },
              { label: 'Minimal List', value: 'minimal' },
              { label: 'Grid Card', value: 'grid' },
              { label: 'Magazine Style', value: 'magazine' },
            ]}
            onChange={(val) => setAttributes({ cardStyle: val })}
          />
        </PanelBody>

        <PanelBody title="Card Design" initialOpen={false}>
          <p
            style={{
              fontSize: '10px',
              fontWeight: 500,
              textTransform: 'uppercase',
              letterSpacing: '0.06em',
              color: 'var(--color-text-secondary)',
              marginBottom: 8,
            }}
          >
            Card
          </p>
          <QFColorControl
            label="Background"
            value={attributes.cardBackgroundColor}
            onChange={(val) => setAttributes({ cardBackgroundColor: val })}
          />
          {attributes.showImage === 'yes' && (
            <div style={{ marginBottom: 10 }}>
              <ToggleGroupControl
                label="Image ratio"
                value={attributes.cardImageRatio}
                onChange={(val) => setAttributes({ cardImageRatio: val })}
                isBlock
              >
                <ToggleGroupControlOption value="16/9" label="16:9" />
                <ToggleGroupControlOption value="4/3" label="4:3" />
                <ToggleGroupControlOption value="1/1" label="1:1" />
              </ToggleGroupControl>
            </div>
          )}
          <RangeControl
            label="Border radius (px)"
            value={attributes.cardBorderRadius}
            onChange={(val) => setAttributes({ cardBorderRadius: val })}
            min={0}
            max={40}
          />
          <ToggleGroupControl
            label="Card shadow"
            value={attributes.cardShadow}
            onChange={(val) => setAttributes({ cardShadow: val })}
            isBlock
          >
            <ToggleGroupControlOption value="none" label="None" />
            <ToggleGroupControlOption value="soft" label="Soft" />
            <ToggleGroupControlOption value="strong" label="Strong" />
          </ToggleGroupControl>
          <AlignmentToggleGroup
            label="Content alignment"
            attrName="cardContentAlignment"
            value={attributes.cardContentAlignment}
            setAttributes={setAttributes}
          />

          {attributes.showTitle === 'yes' && (
            <>
              <hr
                style={{
                  border: 'none',
                  borderTop: '0.5px solid var(--color-border-tertiary)',
                  margin: '12px 0',
                }}
              />
              <p
                style={{
                  fontSize: '10px',
                  fontWeight: 500,
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  color: 'var(--color-text-secondary)',
                  marginBottom: 8,
                }}
              >
                Title
              </p>
              <QFColorControl
                label="Color"
                value={attributes.cardTitleColor}
                onChange={(val) => setAttributes({ cardTitleColor: val })}
              />
              <AlignmentToggleGroup
                label="Align"
                attrName="titleAlign"
                value={attributes.titleAlign}
                setAttributes={setAttributes}
              />
              <TypographyToolsPanel
                prefix="cardTitle"
                panelLabel="Title typography"
                show
                attributes={attributes}
                setAttributes={setAttributes}
              />
            </>
          )}

          {attributes.showExcerpt === 'yes' && (
            <>
              <hr
                style={{
                  border: 'none',
                  borderTop: '0.5px solid var(--color-border-tertiary)',
                  margin: '12px 0',
                }}
              />
              <p
                style={{
                  fontSize: '10px',
                  fontWeight: 500,
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  color: 'var(--color-text-secondary)',
                  marginBottom: 8,
                }}
              >
                Excerpt
              </p>
              <QFColorControl
                label="Color"
                value={attributes.cardExcerptColor}
                onChange={(val) => setAttributes({ cardExcerptColor: val })}
              />
              <AlignmentToggleGroup
                label="Align"
                attrName="excerptAlign"
                value={attributes.excerptAlign}
                setAttributes={setAttributes}
              />
              <TypographyToolsPanel
                prefix="cardExcerpt"
                panelLabel="Excerpt typography"
                show
                attributes={attributes}
                setAttributes={setAttributes}
              />
            </>
          )}

          {(attributes.showDate === 'yes' ||
            attributes.showAuthor === 'yes') && (
            <>
              <hr
                style={{
                  border: 'none',
                  borderTop: '0.5px solid var(--color-border-tertiary)',
                  margin: '12px 0',
                }}
              />
              <p
                style={{
                  fontSize: '10px',
                  fontWeight: 500,
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  color: 'var(--color-text-secondary)',
                  marginBottom: 8,
                }}
              >
                Meta
              </p>
              <QFColorControl
                label="Color"
                value={attributes.cardMetaColor}
                onChange={(val) => setAttributes({ cardMetaColor: val })}
              />
              <AlignmentToggleGroup
                label="Align"
                attrName="metaAlign"
                value={attributes.metaAlign}
                setAttributes={setAttributes}
              />
              {attributes.showDate === 'yes' && (
                <TypographyToolsPanel
                  prefix="cardDate"
                  panelLabel="Date typography"
                  show
                  attributes={attributes}
                  setAttributes={setAttributes}
                />
              )}
              {attributes.showAuthor === 'yes' && (
                <TypographyToolsPanel
                  prefix="cardAuthor"
                  panelLabel="Author typography"
                  show
                  attributes={attributes}
                  setAttributes={setAttributes}
                />
              )}
            </>
          )}

          {attributes.showReadMore === 'yes' && (
            <>
              <hr
                style={{
                  border: 'none',
                  borderTop: '0.5px solid var(--color-border-tertiary)',
                  margin: '12px 0',
                }}
              />
              <p
                style={{
                  fontSize: '10px',
                  fontWeight: 500,
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  color: 'var(--color-text-secondary)',
                  marginBottom: 8,
                }}
              >
                Button
              </p>
              <QFColorControl
                label="Color"
                value={attributes.cardButtonColor}
                onChange={(val) => setAttributes({ cardButtonColor: val })}
              />
              <AlignmentToggleGroup
                label="Align"
                attrName="buttonAlign"
                value={attributes.buttonAlign}
                setAttributes={setAttributes}
              />
              <TypographyToolsPanel
                prefix="cardButton"
                panelLabel="Button typography"
                show
                attributes={attributes}
                setAttributes={setAttributes}
              />
            </>
          )}
        </PanelBody>

        <PanelBody title="Results Summary" initialOpen={false}>
          <ToggleControl
            label="Show Results Summary"
            checked={attributes.showResultsSummary === 'yes'}
            onChange={(val) =>
              setAttributes({ showResultsSummary: val ? 'yes' : 'no' })
            }
          />
          {attributes.showResultsSummary === 'yes' && (
            <>
              <ToggleGroupControl
                label="Position"
                value={attributes.resultsSummaryPosition}
                onChange={(val) =>
                  setAttributes({ resultsSummaryPosition: val })
                }
                isBlock
              >
                <ToggleGroupControlOption
                  value="above_grid"
                  label="Above Grid"
                />
                <ToggleGroupControlOption
                  value="above_pagination"
                  label="Above Pagination"
                />
                <ToggleGroupControlOption
                  value="below_pagination"
                  label="Below Pagination"
                />
              </ToggleGroupControl>
              <p>
                <strong>Typography &amp; color</strong>
              </p>
              <QFColorControl
                label="Text color"
                value={attributes.resultsSummaryColor}
                onChange={(val) =>
                  setAttributes({ resultsSummaryColor: val })
                }
              />
              <AlignmentToggleGroup
                label="Results Summary Align"
                attrName="resultsSummaryAlign"
                value={attributes.resultsSummaryAlign}
                setAttributes={setAttributes}
              />
              <TypographyToolsPanel
                prefix="resultsSummary"
                panelLabel="Results summary typography"
                show
                attributes={attributes}
                setAttributes={setAttributes}
              />
            </>
          )}
        </PanelBody>

        <PanelBody title="Content Fields" initialOpen={false}>
          <ToggleControl
            label="Show Title"
            checked={attributes.showTitle === 'yes'}
            onChange={(val) => setAttributes({ showTitle: val ? 'yes' : 'no' })}
          />
          <ToggleControl
            label="Show Excerpt"
            checked={attributes.showExcerpt === 'yes'}
            onChange={(val) =>
              setAttributes({ showExcerpt: val ? 'yes' : 'no' })
            }
          />
          {attributes.showExcerpt === 'yes' && (
            <RangeControl
              label="Excerpt Length"
              value={attributes.excerptLength}
              onChange={(val) => setAttributes({ excerptLength: val })}
              min={10}
              max={500}
              step={10}
            />
          )}
          <ToggleControl
            label="Show Read More Button"
            checked={attributes.showReadMore === 'yes'}
            onChange={(val) =>
              setAttributes({ showReadMore: val ? 'yes' : 'no' })
            }
          />
          {attributes.showReadMore === 'yes' && (
            <ToggleGroupControl
              label="Button Position"
              value={attributes.cardButtonPosition}
              onChange={(val) =>
                setAttributes({ cardButtonPosition: val })
              }
              isBlock
            >
              <ToggleGroupControlOption value="top" label="Top" />
              <ToggleGroupControlOption value="bottom" label="Bottom" />
            </ToggleGroupControl>
          )}
          <ToggleControl
            label="Show Date"
            checked={attributes.showDate === 'yes'}
            onChange={(val) => setAttributes({ showDate: val ? 'yes' : 'no' })}
          />
          <ToggleControl
            label="Show Author"
            checked={attributes.showAuthor === 'yes'}
            onChange={(val) =>
              setAttributes({ showAuthor: val ? 'yes' : 'no' })
            }
          />
          <ToggleControl
            label="Show Featured Image"
            checked={attributes.showImage === 'yes'}
            onChange={(val) => setAttributes({ showImage: val ? 'yes' : 'no' })}
          />
          {attributes.showImage === 'yes' && (
            <SelectControl
              label="Image Size"
              value={attributes.imageSize}
              options={[
                { label: 'Thumbnail', value: 'thumbnail' },
                { label: 'Medium', value: 'medium' },
                { label: 'Large', value: 'large' },
                { label: 'Full', value: 'full' },
              ]}
              onChange={(val) => setAttributes({ imageSize: val })}
            />
          )}
          <ToggleGroupControl
            label="Link Target"
            value={attributes.linkTarget}
            onChange={(val) => setAttributes({ linkTarget: val })}
            isBlock
          >
            <ToggleGroupControlOption value="_self" label="Same Window" />
            <ToggleGroupControlOption value="_blank" label="New Window" />
          </ToggleGroupControl>
        </PanelBody>

        <PanelBody title="Pagination" initialOpen={false}>
          <ToggleControl
            label="Show Pagination"
            checked={attributes.showPagination === 'yes'}
            onChange={(val) =>
              setAttributes({ showPagination: val ? 'yes' : 'no' })
            }
          />
          {attributes.showPagination === 'yes' && (
            <>
              <SelectControl
                label="Pagination Type"
                value={attributes.paginationType}
                options={[
                  {
                    label: 'Standard (Page Numbers)',
                    value: 'standard',
                  },
                ]}
                onChange={(val) => setAttributes({ paginationType: val })}
              />
              <p
                style={{
                  fontSize: '11px',
                  color: '#999',
                  fontStyle: 'italic',
                }}
              >
                AJAX, Load More, and Infinite Scroll available in{' '}
                <a
                  href="https://queryforgeplugin.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  style={{ color: '#5c4bde' }}
                >
                  Pro
                </a>
              </p>
              <TextControl
                label="Previous Text"
                value={attributes.paginationPrevText}
                onChange={(val) => setAttributes({ paginationPrevText: val })}
                placeholder="« Previous"
              />
              <TextControl
                label="Next Text"
                value={attributes.paginationNextText}
                onChange={(val) => setAttributes({ paginationNextText: val })}
                placeholder="Next »"
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className="qf-block-toolbar">
          <Button
            variant={hasQuery ? 'secondary' : 'primary'}
            onClick={() => setIsModalOpen(true)}
          >
            {hasQuery ? 'Edit Query' : '⚡ Open Query Builder'}
          </Button>
        </div>

        {hasQuery ? (
          <ServerSideRender
            block="query-forge/builder"
            attributes={attributes}
            LoadingResponsePlaceholder={() => (
              <div className="qf-ssr-loading">
                <p>Loading preview…</p>
              </div>
            )}
          />
        ) : (
          <Placeholder
            label="Query Forge"
            instructions="Open the Query Builder to configure your query."
          />
        )}
      </div>

      {isModalOpen &&
        createPortal(
          <QueryBuilderModal
            initialData={attributes.graphState || attributes.logicJson}
            onSave={(data) => {
              setAttributes({
                graphState: data.graphState,
                logicJson: data.logicJson,
              });
              setIsModalOpen(false);
            }}
            onClose={() => setIsModalOpen(false)}
          />,
          portalTarget
        )}
    </>
  );
}

// Always register client-side with full block.json metadata + edit/save.
// Do not gate on getBlockType() — it can be undefined when domReady runs, which
// previously skipped registration and made the block vanish / show as invalid.
const { $schema, ...blockSettings } = metadata;
registerBlockType(blockSettings.name, {
  ...blockSettings,
  edit: EditComponent,
  save: () => null,
});
