import { createContext, useContext, useEffect, useState, ReactNode } from "react"

export type Theme = "light" | "dark" | "solarized"

interface ThemeContextType {
  theme: Theme
  setTheme: (theme: Theme) => void
  toggleTheme: () => void
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined)

const THEMES: Theme[] = ["light", "dark", "solarized"]

function resolveTheme(value: string | null): Theme {
  return value && THEMES.includes(value as Theme) ? (value as Theme) : "light"
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>(() => {
    if (typeof window !== "undefined") {
      return resolveTheme(localStorage.getItem("theme"))
    }
    return "light"
  })

  useEffect(() => {
    const root = window.document.documentElement
    root.classList.remove("light", "dark", "solarized")
    root.classList.add(theme)
    localStorage.setItem("theme", theme)
  }, [theme])

  const toggleTheme = () => setTheme((current) => {
    const index = THEMES.indexOf(current)
    return THEMES[(index + 1) % THEMES.length]
  })

  return (
    <ThemeContext.Provider value={{ theme, setTheme, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  )
}

export function useTheme() {
  const context = useContext(ThemeContext)
  if (context === undefined) {
    throw new Error("useTheme must be used within a ThemeProvider")
  }
  return context
}
