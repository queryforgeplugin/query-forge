// React is provided by WordPress via wp-element (available globally)
const { useEffect } = React;

const UpsellModal = ({ isOpen, onClose, featureName, description }) => {
  // Close on Escape key
  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      return () => {
        document.removeEventListener('keydown', handleEscape);
      };
    }
  }, [isOpen, onClose]);

  if (!isOpen) {
    return null;
  }

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(0, 0, 0, 0.7)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 10000,
      }}
      onClick={onClose}
    >
      <div
        style={{
          background: '#1e1e1e',
          border: '1px solid #444',
          borderRadius: '8px',
          padding: '30px',
          maxWidth: '500px',
          width: '90%',
          color: '#fff',
          boxShadow: '0 4px 20px rgba(0, 0, 0, 0.5)',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <h2 style={{ margin: '0 0 15px 0', fontSize: '24px', color: '#fff' }}>
          Unlock {featureName}
        </h2>
        <p style={{ margin: '0 0 25px 0', fontSize: '14px', color: '#a0aec0', lineHeight: '1.6' }}>
          This is a Pro feature. Upgrade to Query Forge Pro to unlock {description}.
        </p>
        <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end' }}>
          <button
            onClick={onClose}
            style={{
              padding: '10px 20px',
              background: 'transparent',
              color: '#fff',
              border: '1px solid #555',
              borderRadius: '4px',
              cursor: 'pointer',
              fontSize: '14px',
            }}
          >
            Close
          </button>
          <button
            onClick={() => {
              window.open('https://queryforgeplugin.com', '_blank');
            }}
            style={{
              padding: '10px 20px',
              background: '#5c4bde',
              color: '#fff',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer',
              fontSize: '14px',
              fontWeight: 'bold',
            }}
          >
            Upgrade to Pro
          </button>
        </div>
      </div>
    </div>
  );
};

export default UpsellModal;
